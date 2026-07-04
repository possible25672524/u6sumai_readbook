<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ProcessingJob;
use App\Services\DocumentStorageService;
use App\Services\OcrService;
use App\Services\TextChunkerService;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs Tesseract OCR on a PDF or image document,
 * saves extracted text, and chunks it for embedding.
 *
 * Queue: 'ocr' (long-running, resource-intensive)
 */
class OcrDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600; // 10 minutes (large PDFs)

    public function __construct(
        private readonly int $documentId,
        private readonly int $processingJobId
    ) {
        $this->onQueue('ocr');
    }

    public function handle(
        DocumentStorageService $storage,
        OcrService $ocr,
        TextChunkerService $chunker
    ): void {
        $document      = Document::findOrFail($this->documentId);
        $processingJob = ProcessingJob::findOrFail($this->processingJobId);

        $processingJob->start();
        Log::info('OcrDocumentJob started', ['document_id' => $this->documentId]);

        try {
            // 1. Download file from MinIO to local temp
            $localPath = $storage->downloadToTemp($document->file_path);

            try {
                // 2. Run OCR
                $result = $ocr->extract($localPath, $document->mime_type);

                // 3. Save extracted text
                $document->update([
                    'extracted_text' => $result['text'],
                    'page_count'     => $result['page_count'],
                ]);

                // 4. Chunk text and save to document_chunks
                $chunks    = $chunker->chunk($result['text']);
                $avgConf   = $result['confidence'];

                DB::transaction(function () use ($document, $chunks, $avgConf) {
                    // Remove old chunks (re-process scenario)
                    $document->chunks()->delete();

                    $rows = array_map(fn($chunk) => [
                        'document_id'    => $document->id,
                        'chunk_index'    => $chunk['index'],
                        'content'        => $chunk['content'],
                        'token_count'    => $chunk['token_count'],
                        'char_start'     => $chunk['char_start'],
                        'char_end'       => $chunk['char_end'],
                        'ocr_confidence' => $avgConf,
                        'chroma_id'      => (string) Str::uuid(), // must be set manually — bulk insert bypasses model events
                        'is_embedded'    => false,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ], $chunks);

                    // Insert in batches
                    foreach (array_chunk($rows, 500) as $batch) {
                        DocumentChunk::insert($batch);
                    }
                });

                $processingJob->complete([
                    'page_count'     => $result['page_count'],
                    'chunk_count'    => count($chunks),
                    'avg_confidence' => $avgConf,
                ]);

                Log::info('OcrDocumentJob completed', [
                    'document_id' => $this->documentId,
                    'pages'       => $result['page_count'],
                    'chunks'      => count($chunks),
                ]);
            } finally {
                // Always clean up local temp file
                if (file_exists($localPath)) {
                    unlink($localPath);
                }
            }
        } catch (Throwable $e) {
            $processingJob->fail($e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 1000)]);
            Log::error('OcrDocumentJob failed', [
                'document_id' => $this->documentId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('OcrDocumentJob permanently failed', [
            'document_id' => $this->documentId,
            'error'       => $e->getMessage(),
        ]);

        $document = Document::find($this->documentId);
        $document?->markAsFailed();

        ProcessingJob::find($this->processingJobId)?->fail(
            'Job permanently failed after ' . $this->tries . ' attempts: ' . $e->getMessage()
        );
    }
}
