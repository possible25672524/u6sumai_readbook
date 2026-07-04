<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessingJobResource;
use App\Models\Document;
use App\Models\ProcessingJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessingJobController extends Controller
{
    // ─── GET /api/documents/{document}/jobs ───────────────────────

    public function index(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $jobs = $document->processingJobs()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'document_id' => $document->id,
            'status'      => $document->status,
            'jobs'        => ProcessingJobResource::collection($jobs),
        ]);
    }

    // ─── GET /api/jobs/{job} ──────────────────────────────────────

    public function show(Request $request, ProcessingJob $job): JsonResponse
    {
        // User must own the parent document
        $this->authorize('view', $job->document);

        return response()->json([
            'job' => new ProcessingJobResource($job),
        ]);
    }

    // ─── GET /api/admin/jobs ──────────────────────────────────────
    // Admin-only: view all failed jobs across system

    public function adminIndex(Request $request): JsonResponse
    {
        // Route is already under middleware('role:admin'); this Gate check is
        // a defence-in-depth guard using the correct semantic permission.
        $this->authorize('view-system-stats', User::class);

        $status  = $request->query('status', 'failed');
        $perPage = min((int) $request->query('per_page', 20), 100);

        $jobs = ProcessingJob::with('document:id,title,user_id')
            ->where('status', $status)
            ->latest()
            ->paginate($perPage);

        return response()->json($jobs);
    }
}
