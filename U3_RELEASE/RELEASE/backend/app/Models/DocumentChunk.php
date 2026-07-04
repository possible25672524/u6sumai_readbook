<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'token_count',
        'page_number',
        'char_start',
        'char_end',
        'chroma_id',
        'is_embedded',
        'ocr_confidence',
    ];

    protected $casts = [
        'is_embedded'    => 'boolean',
        'ocr_confidence' => 'float',
    ];

    // ─── Boot ─────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $chunk) {
            if (empty($chunk->chroma_id)) {
                $chunk->chroma_id = (string) Str::uuid();
            }
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeEmbedded($query)
    {
        return $query->where('is_embedded', true);
    }

    public function scopeNotEmbedded($query)
    {
        return $query->where('is_embedded', false);
    }

    // ─── Relations ────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
