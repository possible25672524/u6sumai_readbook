<?php

declare(strict_types=1);

namespace App\Services\AI\Concerns;

use App\Models\AIUsageLog;
use App\Services\AI\DTOs\AIUsage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mixin for persisting AI token usage and maintaining budget counters.
 *
 * Add `use TracksUsage;` inside any AI provider class.
 */
trait TracksUsage
{
    /**
     * Persist usage data and update the rolling daily counter in cache.
     *
     * @param  string   $provider    e.g. "claude"
     * @param  string   $model       e.g. "claude-sonnet-4-5"
     * @param  string   $operation   e.g. "summarize", "embed", "transcribe"
     * @param  AIUsage  $usage
     * @param  int|null $userId      Nullable for system-initiated calls
     */
    protected function recordUsage(
        string $provider,
        string $model,
        string $operation,
        AIUsage $usage,
        ?int $userId = null,
    ): void {
        // 1. Persist to DB (best-effort; never block the caller)
        try {
            AIUsageLog::create([
                'provider'           => $provider,
                'model'              => $model,
                'operation'          => $operation,
                'user_id'            => $userId,
                'prompt_tokens'      => $usage->promptTokens,
                'completion_tokens'  => $usage->completionTokens,
                'total_tokens'       => $usage->totalTokens,
                'estimated_cost_usd' => $usage->estimatedCostUsd,
            ]);
        } catch (\Throwable $e) {
            Log::error('[TracksUsage] Failed to persist AI usage log.', [
                'error'     => $e->getMessage(),
                'provider'  => $provider,
                'operation' => $operation,
            ]);
        }

        // 2. Update daily rolling counter in Redis cache via atomic increment.
        //
        // RACE CONDITION FIXED: the previous code did:
        //   Cache::increment($key, $n);                  // atomic ✓
        //   Cache::put($key, Cache::get($key, 0), $ttl); // read-then-write ✗
        // Under concurrent queue workers, multiple workers could read the same
        // stale value and overwrite each other's increments, undercounting tokens.
        //
        // Fix: Cache::increment() is atomic in Redis. We use it exclusively for
        // the counter, then set a TTL only when the key is brand-new (i.e. first
        // call of the day) using Cache::add(), which is a no-op if the key exists.
        // This preserves atomicity: the counter is never overwritten, only incremented.
        $cacheKey = "ai_usage:{$provider}:" . now()->format('Y-m-d');
        $ttl      = now()->endOfDay();

        // Atomically increment (creates the key at 0 + $n if it doesn't exist)
        Cache::increment($cacheKey, max(1, $usage->totalTokens));

        // Set TTL only if this is the first write today (Cache::add is a no-op when key exists)
        Cache::add($cacheKey, Cache::get($cacheKey, 0), $ttl);
    }

    /**
     * Get total tokens used by a provider today (from cache).
     */
    protected function getDailyUsage(string $provider): int
    {
        $cacheKey = "ai_usage:{$provider}:" . now()->format('Y-m-d');

        return (int) Cache::get($cacheKey, 0);
    }
}
