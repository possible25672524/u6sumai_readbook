<?php

namespace App\Listeners;

use App\Events\ProcessingFailedEvent;
use App\Notifications\DocumentProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleProcessingFailed implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(ProcessingFailedEvent $event): void
    {
        $document = $event->document;

        Log::error('Document processing pipeline failed', [
            'document_id' => $document->id,
            'reason'      => $event->reason,
        ]);

        // Notify owner
        $document->user?->notify(new DocumentProcessedNotification($document));
    }
}
