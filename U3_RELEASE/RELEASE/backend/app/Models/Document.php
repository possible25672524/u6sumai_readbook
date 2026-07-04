<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    // Source type constants
    const SOURCE_PDF         = 'pdf';
    const SOURCE_DOCX        = 'docx';
    const SOURCE_TXT         = 'txt';
    const SOURCE_IMAGE       = 'image';
    const SOURCE_AUDIO       = 'audio';
    const SOURCE_VIDEO       = 'video';
    const SOURCE_YOUTUBE     = 'youtube';
    const SOURCE_GOOGLE_DRIVE = 'google_drive';

    // Status constants
    const STATUS_PENDING    = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_FAILED     = 'failed';

    // Visibility constants
    const VISIBILITY_PRIVATE = 'private';
    const VISIBILITY_SHARED  = 'shared';
    const VISIBILITY_PUBLIC  = 'public';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'source_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'source_url',
        'status',
        'extracted_text',
        'language',
        'page_count',
        'duration_seconds',
        'visibility',
        'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'file_size'        => 'integer',
        'page_count'       => 'integer',
        'duration_seconds' => 'integer',
    ];

    protected $hidden = [
        'extracted_text', // large field; load explicitly when needed
        'file_path',      // internal MinIO path; never expose in API — use /download endpoint instead
    ];

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    public function isFileSource(): bool
    {
        return in_array($this->source_type, [
            self::SOURCE_PDF,
            self::SOURCE_DOCX,
            self::SOURCE_TXT,
            self::SOURCE_IMAGE,
            self::SOURCE_AUDIO,
            self::SOURCE_VIDEO,
        ]);
    }

    public function isAudioSource(): bool
    {
        return in_array($this->source_type, [self::SOURCE_AUDIO, self::SOURCE_VIDEO]);
    }

    public function isUrlSource(): bool
    {
        return in_array($this->source_type, [self::SOURCE_YOUTUBE, self::SOURCE_GOOGLE_DRIVE]);
    }

    public function needsOcr(): bool
    {
        return in_array($this->source_type, [self::SOURCE_IMAGE, self::SOURCE_PDF]);
    }

    public function needsTranscription(): bool
    {
        return $this->isAudioSource();
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    // ─── Relations ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'document_categories');
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(ProcessingJob::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }

    public function transcript(): HasOne
    {
        return $this->hasOne(Transcript::class);
    }
}
