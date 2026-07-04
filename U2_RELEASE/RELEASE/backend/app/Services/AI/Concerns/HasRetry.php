<?php

declare(strict_types=1);

namespace App\Services\AI\Concerns;

use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Support\Facades\Log;

/**
 * Mixin providing exponential-backoff retry logic for AI provider calls.
 *
 * Usage: add `use HasRetry;` inside any AI provider class.
 */
trait HasRetry
{
    /**
     * Execute a callable with automatic retry on transient failures.
     *
     * @param  callable(): mixed  $operation    The API call to execute
     * @param  string             $context      Log context label (e.g. "claude.chat")
     * @param  int                $maxAttempts  Total attempts including the first try
     * @param  int                $baseDelayMs  Base delay in milliseconds (doubles each retry)
     * @return mixed
     *
     * @throws AIProviderException  When all retries are exhausted
     */
    protected function withRetry(
        callable $operation,
        string $context = 'ai.request',
        int $maxAttempts = 3,
        int $baseDelayMs = 500,
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return $operation();
            } catch (AIRateLimitException $e) {
                // For rate limits, respect the Retry-After header if provided
                $delayMs = $e->retryAfterSeconds
                    ? $e->retryAfterSeconds * 1000
                    : $this->backoffDelay($attempt, $baseDelayMs);

                Log::warning("[{$context}] Rate limited on attempt {$attempt}/{$maxAttempts}.", [
                    'retry_after_ms' => $delayMs,
                    'provider'       => $e->provider,
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($delayMs * 1000);
                }

                $lastException = $e;
            } catch (AIProviderException $e) {
                // Only retry on server-side errors (5xx); propagate client errors (4xx) immediately
                if ($e->statusCode >= 400 && $e->statusCode < 500 && $e->statusCode !== 429) {
                    throw $e;
                }

                $delayMs = $this->backoffDelay($attempt, $baseDelayMs);

                Log::warning("[{$context}] Transient error on attempt {$attempt}/{$maxAttempts}.", [
                    'error'          => $e->getMessage(),
                    'status_code'    => $e->statusCode,
                    'retry_after_ms' => $delayMs,
                ]);

                if ($attempt < $maxAttempts) {
                    usleep($delayMs * 1000);
                }

                $lastException = $e;
            }
        }

        Log::error("[{$context}] All {$maxAttempts} attempts failed.", [
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new AIProviderException(
            "Operation failed after {$maxAttempts} attempts in context '{$context}'.",
            provider: $context,
        );
    }

    /**
     * Compute exponential backoff with jitter to avoid thundering herd.
     *
     * @param  int  $attempt    Current attempt number (1-based)
     * @param  int  $baseDelayMs
     * @return int  Delay in milliseconds
     */
    private function backoffDelay(int $attempt, int $baseDelayMs): int
    {
        $exponential = $baseDelayMs * (2 ** ($attempt - 1));   // 500 → 1000 → 2000
        $jitter      = random_int(0, (int) ($exponential * 0.2)); // ±20% jitter

        return min($exponential + $jitter, 30_000); // cap at 30 s
    }
}
