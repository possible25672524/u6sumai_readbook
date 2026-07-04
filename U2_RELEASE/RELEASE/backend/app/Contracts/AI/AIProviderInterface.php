<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;

/**
 * Core interface for AI text generation providers.
 *
 * Implementations: ClaudeProvider, OpenAIChatProvider
 * Used by: SummarizationService, QuestionGenerationService, RAGChatService
 */
interface AIProviderInterface
{
    /**
     * Send a chat completion request and return a structured response.
     *
     * @param  ChatMessage[]  $messages    Ordered conversation history
     * @param  array<string, mixed>  $options  Provider-specific options (temperature, max_tokens, etc.)
     * @return ChatResponse
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException
     * @throws \App\Services\AI\Exceptions\AIRateLimitException
     */
    public function chat(array $messages, array $options = []): ChatResponse;

    /**
     * Return the canonical provider name (e.g. "claude", "openai").
     */
    public function getProviderName(): string;

    /**
     * Return the default model identifier used by this provider.
     */
    public function getDefaultModel(): string;

    /**
     * Verify the provider is reachable and credentials are valid.
     * Intended for health checks, not called on every request.
     */
    public function ping(): bool;
}
