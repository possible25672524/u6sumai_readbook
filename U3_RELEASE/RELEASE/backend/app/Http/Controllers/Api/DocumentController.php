<?php

namespace App\Http\Controllers\Api;

use App\Events\DocumentUploadedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentChunkResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ProcessingJobResource;
use App\Http\Resources\TranscriptResource;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Services\ChromaDbService;
use App\Services\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentStorageService $storage
    ) {}

    // ─── GET /api/documents ───────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Document::class);

        $query = Document::with(['categories'])
            ->withCount('chunks')
            ->active();

        // Students & teachers only see their own + public/shared
        if (!in_array($request->user()->role?->slug, ['admin'])) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhereIn('visibility', [
                      Document::VISIBILITY_PUBLIC,
                      Document::VISIBILITY_SHARED,
                  ]);
            });
        }

        // Filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('source_type')) {
            $query->where('source_type', $type);
        }
        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $categoryId));
        }
        if ($search = $request->query('search')) {
            $query->where(fn($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
            );
        }

        $perPage  = min((int) ($request->query('per_page', 15)), 100);
        $documents = $query->latest()->paginate($perPage);

        return DocumentResource::collection($documents);
    }

    // ─── POST /api/documents ──────────────────────────────────────

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $validated = $request->validated();
        $user      = $request->user();

        try {
            $document = DB::transaction(function () use ($request, $validated, $user) {
                $data = [
                    'user_id'     => $user->id,
                    'title'       => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'source_type' => $validated['source_type'],
                    'visibility'  => $validated['visibility'] ?? Document::VISIBILITY_PRIVATE,
                    'language'    => $validated['language'] ?? 'th',
                    'status'      => Document::STATUS_PENDING,
                ];

                // Handle file upload
                if ($request->hasFile('file')) {
                    $stored       = $this->storage->store($request->file('file'), $user->id);
                    $data['file_path']  = $stored['path'];
                    $data['file_name']  = $stored['name'];
                    $data['mime_type']  = $stored['mime'];
                    $data['file_size']  = $stored['size'];
                }

                // Handle URL-based sources
                if (!empty($validated['source_url'])) {
                    $data['source_url'] = $validated['source_url'];
                }

                // For plain text, extract immediately (no async needed for small files)
                if ($validated['source_type'] === Document::SOURCE_TXT && $request->hasFile('file')) {
                    $content = file_get_contents($request->file('file')->getRealPath());
                    $data['extracted_text'] = $content;
                }

                $document = Document::create($data);

                // Attach categories
                if (!empty($validated['category_ids'])) {
                    $document->categories()->sync($validated['category_ids']);
                }

                return $document;
            });

            // Dispatch async processing pipeline
            ProcessDocumentJob::dispatch($document->id)
                ->onQueue('default');

            event(new DocumentUploadedEvent($document));

            Log::info('Document uploaded', ['document_id' => $document->id, 'user_id' => $user->id]);

            return response()->json([
                'message'  => 'อัปโหลดเอกสารสำเร็จ กำลังประมวลผล...',
                'document' => new DocumentResource($document->load('categories')),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Document upload failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'อัปโหลดเอกสารไม่สำเร็จ: ' . $e->getMessage()], 500);
        }
    }

    // ─── GET /api/documents/{document} ───────────────────────────

    public function show(Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        $document->load(['categories', 'processingJobs', 'user']);

        return new DocumentResource($document);
    }

    // ─── PUT /api/documents/{document} ───────────────────────────

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($document, $validated) {
            // Only update fields that were explicitly sent in the request.
            // Using array_intersect_key preserves intentional null values (e.g. clearing description).
            $allowed    = ['title', 'description', 'visibility', 'extracted_text'];
            $updateData = array_intersect_key($validated, array_flip($allowed));

            if (!empty($updateData)) {
                $document->update($updateData);
            }

            if (array_key_exists('category_ids', $validated)) {
                $document->categories()->sync($validated['category_ids'] ?? []);
            }

            // If user corrected extracted_text, re-trigger embedding
            if (array_key_exists('extracted_text', $validated) && $document->status === Document::STATUS_COMPLETED) {
                $document->update(['status' => Document::STATUS_PENDING]);
                ProcessDocumentJob::dispatch($document->id)->onQueue('default');
            }
        });

        return response()->json([
            'message'  => 'อัปเดตเอกสารสำเร็จ',
            'document' => new DocumentResource($document->fresh(['categories'])),
        ]);
    }

    // ─── DELETE /api/documents/{document} ────────────────────────

    public function destroy(Document $document, ChromaDbService $chroma): JsonResponse
    {
        $this->authorize('delete', $document);

        DB::transaction(function () use ($document, $chroma) {
            // Delete vectors from ChromaDB
            try {
                $chroma->deleteByDocumentId($document->id);
            } catch (\Throwable $e) {
                Log::warning('ChromaDB cleanup failed on delete', ['document_id' => $document->id]);
            }

            // Delete file from MinIO
            if ($document->file_path) {
                try {
                    $this->storage->delete($document->file_path);
                } catch (\Throwable $e) {
                    Log::warning('MinIO cleanup failed on delete', ['document_id' => $document->id]);
                }
            }

            $document->delete(); // soft delete
        });

        return response()->json(['message' => 'ลบเอกสารสำเร็จ']);
    }

    // ─── POST /api/documents/{document}/reprocess ─────────────────

    public function reprocess(Document $document): JsonResponse
    {
        $this->authorize('reprocess', $document);

        if ($document->status === Document::STATUS_PROCESSING) {
            return response()->json(['message' => 'เอกสารกำลังประมวลผลอยู่แล้ว'], 409);
        }

        $document->update(['status' => Document::STATUS_PENDING]);
        $document->processingJobs()->delete();

        ProcessDocumentJob::dispatch($document->id)->onQueue('default');

        return response()->json(['message' => 'เริ่มประมวลผลเอกสารใหม่']);
    }

    // ─── GET /api/documents/{document}/status ─────────────────────

    public function status(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $jobs = $document->processingJobs()->latest()->get();

        return response()->json([
            'document_id' => $document->id,
            'status'      => $document->status,
            'jobs'        => ProcessingJobResource::collection($jobs),
        ]);
    }

    // ─── GET /api/documents/{document}/chunks ─────────────────────

    public function chunks(Request $request, Document $document): AnonymousResourceCollection
    {
        $this->authorize('viewChunks', $document);

        $perPage = min((int) ($request->query('per_page', 50)), 200);
        $chunks  = $document->chunks()->paginate($perPage);

        return DocumentChunkResource::collection($chunks);
    }

    // ─── GET /api/documents/{document}/transcript ─────────────────

    public function transcript(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        if (!$document->transcript) {
            return response()->json(['message' => 'ไม่พบคำแปลสำหรับเอกสารนี้'], 404);
        }

        return response()->json([
            'transcript' => new TranscriptResource($document->transcript),
        ]);
    }

    // ─── GET /api/documents/{document}/download ───────────────────

    public function download(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        if (!$document->file_path) {
            return response()->json(['message' => 'ไม่มีไฟล์สำหรับเอกสารนี้'], 404);
        }

        $url = $this->storage->temporaryUrl($document->file_path, 15);

        return response()->json(['url' => $url, 'expires_in_minutes' => 15]);
    }
}
