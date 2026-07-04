<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessingJob extends Model
{
    use HasFactory;
    const TYPE_OCR        = 'ocr';
    const TYPE_TRANSCRIBE = 'transcribe';
    const TYPE_EMBED      = 'embed';
    const TYPE_SUMMARIZE  = 'summarize';

    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'document_id',
        'job_type',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'error_context',
        'started_at',
        'completed_at',
        'progress',
        'meta',
    ];

    protected $casts = [
        'error_context' => 'array',
        'meta'          => 'array',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function start(): void
    {
        $this->update([
            'status'     => self::STATUS_PROCESSING,
            'started_at' => now(),
            'attempts'   => $this->attempts + 1,
        ]);
    }

    public function complete(array $meta = []): void
    {
        $this->update([
            'status'       => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'progress'     => 100,
            'meta'         => array_merge($this->meta ?? [], $meta),
        ]);
    }

    public function fail(string $message, array $context = []): void
    {
        $this->update([
            'status'        => self::STATUS_FAILED,
            'error_message' => $message,
            'error_context' => $context,
            'completed_at'  => now(),
        ]);
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    // ─── Relations ────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
