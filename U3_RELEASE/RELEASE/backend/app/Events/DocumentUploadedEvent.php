<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUploadedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Document $document
    ) {}
}
