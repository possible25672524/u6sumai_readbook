<?php

namespace App\Providers;

use App\Events\DocumentProcessedEvent;
use App\Events\DocumentUploadedEvent;
use App\Events\ProcessingFailedEvent;
use App\Listeners\HandleProcessingFailed;
use App\Listeners\SendDocumentProcessedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Phase 2: Document pipeline events
        DocumentUploadedEvent::class => [
            // Could add: log activity, send confirmation, etc.
        ],

        DocumentProcessedEvent::class => [
            SendDocumentProcessedNotification::class,
        ],

        ProcessingFailedEvent::class => [
            HandleProcessingFailed::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
