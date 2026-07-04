<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Normalised response from any AIProviderInterface::chat() call.
 */
final class ChatResponse
{
    public function __construct(
        /** The generated text content */
        public readonly string $content,
        /** Provider name ("claude" | "openai") */
        public readonly string $provider,
        /** Model that produced the response */
        public readonly string $model,
        /** Token counts for cost tracking */
        public readonly AIUsage $usage,
        /**
         * Stop reason: "end_turn" | "max_tokens" | "stop_sequence"
         * Unified across providers (Anthropic uses "end_turn", OpenAI uses "stop")
         */
        public readonly string $stopReason = 'end_turn',
        /** Raw provider response for debugging (never exposed to clients) */
        public readonly ?array $rawResponse = null,
    ) {}

    /**
     * Did the model finish generating normally (not cut off by token limit)?
     */
    public function isComplete(): bool
    {
        return in_array($this->stopReason, ['end_turn', 'stop'], true);
    }

    public function toArray(): array
    {
        return [
            'content'     => $this->content,
            'provider'    => $this->provider,
            'model'       => $this->model,
            'usage'       => $this->usage->toArray(),
            'stop_reason' => $this->stopReason,
            'is_complete' => $this->isComplete(),
        ];
    }
}
