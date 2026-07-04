<?php

namespace App\Listeners;

use App\Events\DocumentProcessedEvent;
use App\Notifications\DocumentProcessedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDocumentProcessedNotification implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(DocumentProcessedEvent $event): void
    {
        $document = $event->document;
        $user     = $document->user;

        if ($user) {
            $user->notify(new DocumentProcessedNotification($document));
        }
    }
}
