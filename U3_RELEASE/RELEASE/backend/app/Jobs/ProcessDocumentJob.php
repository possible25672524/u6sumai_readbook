<?php

namespace App\Jobs;

use App\Events\ProcessingFailedEvent;
use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\OcrDocumentJob;
use App\Jobs\TranscribeAudioJob;
use App\Models\Document;
use App\Models\ProcessingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrator job — dispatched immediately after a document is uploaded.
 * It determines which processing jobs to chain and dispatches them.
 *
 * Pipeline per source type:
 *   pdf/image → OcrDocumentJob → GenerateEmbeddingsJob
 *   audio/video → TranscribeAudioJob → GenerateEmbeddingsJob
 *   txt/docx → (extract inline, small) → GenerateEmbeddingsJob
 *   youtube/google_drive → (URL fetch, not in Phase 2) → GenerateEmbeddingsJob
 */
class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;     // Orchestrator itself should not retry
    public int $timeout = 60;    // 1 minute to dispatch sub-jobs

    public function __construct(
        public readonly int $documentId
    ) {}

    public function handle(): void
    {
        $document = Document::findOrFail($this->documentId);

        Log::info('ProcessDocumentJob started', ['document_id' => $this->documentId]);

        $document->markAsProcessing();

        try {
            if ($document->needsOcr()) {
                // PDF / Image → OCR → Embed
                $processingJob = $this->createProcessingJob($document, ProcessingJob::TYPE_OCR);
                OcrDocumentJob::withChain([
                    new GenerateEmbeddingsJob($this->documentId),
                ])->dispatch($this->documentId, $processingJob->id);

            } elseif ($document->needsTranscription()) {
                // Audio / Video → Whisper → Embed
                $processingJob = $this->createProcessingJob($document, ProcessingJob::TYPE_TRANSCRIBE);
                TranscribeAudioJob::withChain([
                    new GenerateEmbeddingsJob($this->documentId),
                ])->dispatch($this->documentId, $processingJob->id);

            } else {
                // txt / docx / URL-based: text already extracted in controller
                // go straight to embedding
                $embedJob = $this->createProcessingJob($document, ProcessingJob::TYPE_EMBED);
                GenerateEmbeddingsJob::dispatch($this->documentId, $embedJob->id);
            }
        } catch (Throwable $e) {
            Log::error('ProcessDocumentJob failed', [
                'document_id' => $this->documentId,
                'error'       => $e->getMessage(),
            ]);
            $document->markAsFailed();
            event(new ProcessingFailedEvent($document, $e->getMessage()));
            throw $e;
        }
    }

    private function createProcessingJob(Document $document, string $type): ProcessingJob
    {
        return $document->processingJobs()->create([
            'job_type'     => $type,
            'status'       => ProcessingJob::STATUS_PENDING,
            'max_attempts' => 3,
        ]);
    }
}
