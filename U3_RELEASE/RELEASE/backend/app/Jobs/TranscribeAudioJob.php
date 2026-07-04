<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ProcessingJob;
use App\Models\Transcript;
use App\Services\DocumentStorageService;
use App\Services\TextChunkerService;
use App\Services\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Transcribes audio/video using OpenAI Whisper API,
 * saves the transcript, and chunks the text for embedding.
 *
 * Queue: 'transcribe'
 */
class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        private readonly int $documentId,
        private readonly int $processingJobId
    ) {
        $this->onQueue('transcribe');
    }

    public function handle(
        DocumentStorageService $storage,
        TranscriptionService $transcription,
        TextChunkerService $chunker
    ): void {
        $document      = Document::findOrFail($this->documentId);
        $processingJob = ProcessingJob::findOrFail($this->processingJobId);

        $processingJob->start();
        Log::info('TranscribeAudioJob started', ['document_id' => $this->documentId]);

        try {
            $localPath = $storage->downloadToTemp($document->file_path);

            try {
                $result     = $transcription->transcribe($localPath, $document->language ?? 'th');
                $chunkCount = 0; // will be updated inside transaction via reference

                DB::transaction(function () use ($document, $result, $chunker, &$chunkCount) {
                    // Save transcript record
                    Transcript::updateOrCreate(
                        ['document_id' => $document->id],
                        [
                            'content'          => $result['text'],
                            'language'         => $result['language'],
                            'duration_seconds' => (int) $result['duration'],
                            'segments'         => $result['segments'],
                            'model'            => $result['model'],
                        ]
                    );

                    // Save extracted text on document
                    $document->update([
                        'extracted_text'   => $result['text'],
                        'language'         => $result['language'],
                        'duration_seconds' => (int) $result['duration'],
                    ]);

                    // Chunk and save
                    $chunks     = $chunker->chunk($result['text']);
                    $chunkCount = count($chunks);
                    $document->chunks()->delete();

                    $rows = array_map(fn($chunk) => [
                        'document_id' => $document->id,
                        'chunk_index' => $chunk['index'],
                        'content'     => $chunk['content'],
                        'token_count' => $chunk['token_count'],
                        'char_start'  => $chunk['char_start'],
                        'char_end'    => $chunk['char_end'],
                        'chroma_id'   => (string) Str::uuid(), // bulk insert bypasses model boot events
                        'is_embedded' => false,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ], $chunks);

                    foreach (array_chunk($rows, 500) as $batch) {
                        DocumentChunk::insert($batch);
                    }
                });

                $processingJob->complete([
                    'duration_seconds' => (int) $result['duration'],
                    'language'         => $result['language'],
                    'chunk_count'      => $chunkCount, // use stored count — avoids re-chunking
                ]);

                Log::info('TranscribeAudioJob completed', ['document_id' => $this->documentId]);
            } finally {
                if (file_exists($localPath)) {
                    unlink($localPath);
                }
            }
        } catch (Throwable $e) {
            $processingJob->fail($e->getMessage());
            Log::error('TranscribeAudioJob failed', [
                'document_id' => $this->documentId,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Document::find($this->documentId)?->markAsFailed();
        ProcessingJob::find($this->processingJobId)?->fail(
            'Permanently failed: ' . $e->getMessage()
        );
    }
}
