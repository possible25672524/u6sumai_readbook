<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transcript extends Model
{
    protected $fillable = [
        'document_id',
        'content',
        'language',
        'avg_logprob',
        'duration_seconds',
        'segments',
        'provider',
        'model',
    ];

    protected $casts = [
        'segments'         => 'array',
        'avg_logprob'      => 'float',
        'duration_seconds' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
