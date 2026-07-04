<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

/**
 * Thrown when an AI provider returns HTTP 429 (Too Many Requests).
 * Queue jobs should catch this and delay/retry accordingly.
 */
class AIRateLimitException extends AIProviderException
{
    public function __construct(
        string $provider,
        /** Retry-After header value in seconds (null if not provided by API) */
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Rate limit exceeded for provider '{$provider}'."
            . ($retryAfterSeconds ? " Retry after {$retryAfterSeconds}s." : ''),
            $provider,
            429,
            $previous
        );
    }
}
