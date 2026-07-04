<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

/**
 * Base exception for all AI provider failures.
 */
class AIProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly int $statusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
