<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Contracts\AI\AIProviderInterface;
use App\Contracts\AI\EmbeddingProviderInterface;
use App\Contracts\AI\TranscriptionProviderInterface;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;
use App\Services\AI\DTOs\EmbeddingResponse;
use App\Services\AI\DTOs\TranscriptionResponse;
use App\Services\AI\Exceptions\AIProviderException;

/**
 * Central AI provider registry implementing the Strategy Pattern.
 *
 * Resolved from the container as a singleton:
 *   app(AIManager::class)->chat([...])
 *   app(AIManager::class)->embed('text')
 *   app(AIManager::class)->transcribe('/path/audio.mp3')
 *
 * Provider resolution order:
 *   chat()       → default chat provider (claude | openai)
 *   embed()      → always OpenAI text-embedding-3-small
 *   transcribe() → always OpenAI Whisper
 */
final class AIManager
{
    /** @var array<string, AIProviderInterface> */
    private array $chatProviders = [];

    public function __construct(
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly TranscriptionProviderInterface $transcriptionProvider,
        private readonly string $defaultChatProvider = 'claude',
    ) {}

    // ── Registration ──────────────────────────────────────────────────────

    /**
     * Register (or replace) a chat provider by name.
     */
    public function registerChatProvider(string $name, AIProviderInterface $provider): self
    {
        $this->chatProviders[$name] = $provider;

        return $this;
    }

    // ── Chat ──────────────────────────────────────────────────────────────

    /**
     * Send a chat request using the named provider (default: claude).
     *
     * @param  ChatMessage[]  $messages
     * @param  array<string, mixed>  $options  Additional provider options
     * @param  string|null    $provider  Override the default provider ("claude"|"openai")
     */
    public function chat(
        array $messages,
        array $options = [],
        ?string $provider = null,
    ): ChatResponse {
        return $this->resolveChatProvider($provider)->chat($messages, $options);
    }

    /**
     * Convenience: single-turn completion with a plain string prompt.
     */
    public function complete(
        string $prompt,
        ?string $systemPrompt = null,
        array $options = [],
        ?string $provider = null,
    ): ChatResponse {
        $messages = [];

        if ($systemPrompt !== null) {
            $messages[] = ChatMessage::system($systemPrompt);
        }

        $messages[] = ChatMessage::user($prompt);

        return $this->chat($messages, $options, $provider);
    }

    // ── Embedding ────────────────────────────────────────────────────────

    /**
     * Embed a single text string.
     * Always uses OpenAI text-embedding-3-small.
     */
    public function embed(string $text, array $options = []): EmbeddingResponse
    {
        return $this->embeddingProvider->embed($text, $options);
    }

    /**
     * Batch-embed multiple texts in one API call.
     *
     * @param  string[]  $texts
     * @return EmbeddingResponse[]
     */
    public function embedBatch(array $texts, array $options = []): array
    {
        return $this->embeddingProvider->embedBatch($texts, $options);
    }

    // ── Transcription ────────────────────────────────────────────────────

    /**
     * Transcribe an audio file.
     * Always uses OpenAI Whisper.
     */
    public function transcribe(
        string $filePath,
        string $language = 'th',
        array $options = [],
    ): TranscriptionResponse {
        return $this->transcriptionProvider->transcribe($filePath, $language, $options);
    }

    // ── Health Checks ────────────────────────────────────────────────────

    /**
     * Ping all registered chat providers and the embedding provider.
     *
     * @return array<string, bool>  Provider name → reachable
     */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->chatProviders as $name => $provider) {
            $results["chat:{$name}"] = $provider->ping();
        }

        $results['embedding'] = $this->embeddingProvider instanceof AIProviderInterface
            ? $this->embeddingProvider->ping()
            : true; // assume OK if it doesn't implement ping

        return $results;
    }

    // ── Provider Info ────────────────────────────────────────────────────

    public function getDefaultChatProvider(): string
    {
        return $this->defaultChatProvider;
    }

    public function getEmbeddingModel(): string
    {
        return $this->embeddingProvider->getEmbeddingModel();
    }

    public function getEmbeddingDimensions(): int
    {
        return $this->embeddingProvider->getDimensions();
    }

    // ── Internal ─────────────────────────────────────────────────────────

    private function resolveChatProvider(?string $name): AIProviderInterface
    {
        $name ??= $this->defaultChatProvider;

        if (! isset($this->chatProviders[$name])) {
            throw new AIProviderException(
                "Chat provider '{$name}' is not registered. "
                . 'Available: ' . implode(', ', array_keys($this->chatProviders)),
                provider: $name,
            );
        }

        return $this->chatProviders[$name];
    }
}
