<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persists per-call AI token usage for cost tracking and budget alerts.
 *
 * Written by TracksUsage trait after every successful AI API call.
 * Daily rolling totals are also maintained in Redis (keyed by provider + date).
 *
 * @property int         $id
 * @property string      $provider            e.g. "claude", "openai"
 * @property string      $model               e.g. "claude-sonnet-4-5"
 * @property string      $operation           e.g. "summarize:bullet", "embed", "transcribe"
 * @property int|null    $user_id
 * @property int         $prompt_tokens
 * @property int         $completion_tokens
 * @property int         $total_tokens
 * @property float|null  $estimated_cost_usd
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AIUsageLog extends Model
{
    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'provider',
        'model',
        'operation',
        'user_id',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
    ];

    protected $casts = [
        'prompt_tokens'      => 'integer',
        'completion_tokens'  => 'integer',
        'total_tokens'       => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'user_id'            => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Aggregates ───────────────────────────────────────────────────────

    /**
     * Total tokens consumed by a provider today (from DB — use Redis cache for hot path).
     */
    public static function dailyTokensForProvider(string $provider): int
    {
        return (int) static::forProvider($provider)->today()->sum('total_tokens');
    }

    /**
     * Estimated USD cost by provider for a date range.
     */
    public static function costForProvider(
        string $provider,
        \Carbon\Carbon $from,
        \Carbon\Carbon $to,
    ): float {
        return (float) static::forProvider($provider)
            ->whereBetween('created_at', [$from, $to])
            ->sum('estimated_cost_usd');
    }
}
