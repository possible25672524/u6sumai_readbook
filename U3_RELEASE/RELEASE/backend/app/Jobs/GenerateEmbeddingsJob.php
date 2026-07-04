<?php

namespace App\Jobs;

use App\Events\DocumentProcessedEvent;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ProcessingJob;
use App\Services\ChromaDbService;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates OpenAI embeddings for all DocumentChunks of a document
 * and upserts them into ChromaDB.
 *
 * Queue: 'embed'
 */
class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes

    // Batch size: send N chunks to OpenAI per API call
    private const EMBED_BATCH = 50;

    public function __construct(
        private readonly int  $documentId,
        private readonly ?int $processingJobId = null
    ) {
        $this->onQueue('embed');
    }

    public function handle(
        EmbeddingService $embedder,
        ChromaDbService  $chroma
    ): void {
        $document      = Document::findOrFail($this->documentId);
        $processingJob = $this->processingJobId
            ? ProcessingJob::find($this->processingJobId)
            : $document->processingJobs()->create([
                'job_type'     => ProcessingJob::TYPE_EMBED,
                'status'       => ProcessingJob::STATUS_PENDING,
                'max_attempts' => 3,
            ]);

        $processingJob?->start();
        Log::info('GenerateEmbeddingsJob started', ['document_id' => $this->documentId]);

        try {
            $chunks = $document->chunks()->notEmbedded()->get();

            if ($chunks->isEmpty()) {
                Log::info('GenerateEmbeddingsJob: no chunks to embed', ['document_id' => $this->documentId]);
                $processingJob?->complete(['chunk_count' => 0]);
                $document->markAsCompleted();
                event(new DocumentProcessedEvent($document));
                return;
            }

            $totalChunks    = $chunks->count();
            $processedCount = 0;

            // Process in batches
            foreach ($chunks->chunk(self::EMBED_BATCH) as $batch) {
                // Generate embeddings
                $vectors = $embedder->embedChunks($batch);

                // Prepare ChromaDB data
                $documents = [];
                $metadatas = [];

                foreach ($batch as $chunk) {
                    $cid = $chunk->chroma_id;
                    $documents[$cid] = $chunk->content;
                    $metadatas[$cid] = [
                        'document_id' => $document->id,
                        'user_id'     => $document->user_id,
                        'chunk_index' => $chunk->chunk_index,
                        'page_number' => $chunk->page_number ?? 0,
                        'source_type' => $document->source_type,
                    ];
                }

                // Upsert into ChromaDB
                $chroma->upsert($vectors, $documents, $metadatas);

                // Mark chunks as embedded in MariaDB
                $chromaIds = $batch->pluck('chroma_id')->toArray();
                DocumentChunk::whereIn('chroma_id', $chromaIds)
                    ->update(['is_embedded' => true]);

                $processedCount += count($batch);

                // Update progress
                $progress = (int) (($processedCount / $totalChunks) * 100);
                $processingJob?->update(['progress' => $progress]);
            }

            $processingJob?->complete(['chunk_count' => $totalChunks]);
            $document->markAsCompleted();

            event(new DocumentProcessedEvent($document));

            Log::info('GenerateEmbeddingsJob completed', [
                'document_id' => $this->documentId,
                'chunks'      => $totalChunks,
            ]);
        } catch (Throwable $e) {
            $processingJob?->fail($e->getMessage());
            Log::error('GenerateEmbeddingsJob failed', [
                'document_id' => $this->documentId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Document::find($this->documentId)?->markAsFailed();
        if ($this->processingJobId) {
            ProcessingJob::find($this->processingJobId)?->fail('Permanently failed: ' . $e->getMessage());
        }
    }
}
