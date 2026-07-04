<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProviderInterface;
use App\Services\AI\Concerns\HasRetry;
use App\Services\AI\Concerns\TracksUsage;
use App\Services\AI\DTOs\AIUsage;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude provider implementation.
 *
 * Calls the Anthropic Messages API (v1/messages).
 * Used for: summarisation, question generation, RAG chatbot responses.
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
final class ClaudeProvider implements AIProviderInterface
{
    use HasRetry, TracksUsage;

    private const API_BASE     = 'https://api.anthropic.com';
    private const API_VERSION  = '2023-06-01';
    private const PROVIDER     = 'claude';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int    $maxTokens,
        private readonly int    $timeoutSeconds,
        private readonly int    $maxRetries,
    ) {}

    // ── AIProviderInterface ────────────────────────────────────────────────

    public function chat(array $messages, array $options = []): ChatResponse
    {
        $systemPrompt = $this->extractSystemPrompt($messages);
        $userMessages = $this->filterUserMessages($messages);

        $payload = [
            'model'      => $options['model']      ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'messages'   => array_map(
                fn (ChatMessage $m) => $m->toAnthropicArray(),
                $userMessages
            ),
        ];

        if ($systemPrompt !== null) {
            $payload['system'] = $systemPrompt;
        }

        // Optional overrides
        foreach (['temperature', 'top_p', 'top_k', 'stop_sequences'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        $model   = $payload['model'];
        $context = "claude.chat.{$model}";

        $rawResponse = $this->withRetry(
            fn () => $this->makeRequest('/v1/messages', $payload),
            context: $context,
            maxAttempts: $this->maxRetries,
        );

        $usage = AIUsage::fromAnthropic($rawResponse['usage']);

        $this->recordUsage(
            provider:  self::PROVIDER,
            model:     $model,
            operation: $options['operation'] ?? 'chat',
            usage:     $usage,
            userId:    $options['user_id'] ?? null,
        );

        Log::debug("[ClaudeProvider] chat completed.", [
            'model'  => $model,
            'tokens' => $usage->totalTokens,
        ]);

        return new ChatResponse(
            content:     $rawResponse['content'][0]['text'] ?? '',
            provider:    self::PROVIDER,
            model:       $model,
            usage:       $usage,
            stopReason:  $rawResponse['stop_reason'] ?? 'end_turn',
            rawResponse: config('app.debug') ? $rawResponse : null,
        );
    }

    public function getProviderName(): string
    {
        return self::PROVIDER;
    }

    public function getDefaultModel(): string
    {
        return $this->model;
    }

    public function ping(): bool
    {
        try {
            $this->makeRequest('/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1,
                'messages'   => [['role' => 'user', 'content' => 'ping']],
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Internal ───────────────────────────────────────────────────────────

    /**
     * Make an authenticated POST request to the Anthropic API.
     *
     * @param  string  $path
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws AIRateLimitException
     * @throws AIProviderException
     */
    private function makeRequest(string $path, array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])
            ->timeout($this->timeoutSeconds)
            ->post(self::API_BASE . $path, $payload);
        } catch (ConnectionException $e) {
            throw new AIProviderException(
                "Connection to Anthropic API failed: {$e->getMessage()}",
                provider: self::PROVIDER,
                previous: $e,
            );
        }

        return $this->parseResponse($response);
    }

    /**
     * @param  Response  $response
     * @return array<string, mixed>
     *
     * @throws AIRateLimitException
     * @throws AIProviderException
     */
    private function parseResponse(Response $response): array
    {
        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('retry-after') ?: null;
            throw new AIRateLimitException(self::PROVIDER, $retryAfter);
        }

        if (! $response->successful()) {
            $body    = $response->json();
            $message = $body['error']['message'] ?? $response->body();

            throw new AIProviderException(
                "Anthropic API error [{$response->status()}]: {$message}",
                provider:   self::PROVIDER,
                statusCode: $response->status(),
            );
        }

        return $response->json();
    }

    /**
     * Extract the first system message content to pass as the Anthropic
     * `system` parameter (Anthropic doesn't accept system role in messages[]).
     */
    private function extractSystemPrompt(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message instanceof ChatMessage && $message->role === 'system') {
                return $message->content;
            }
        }

        return null;
    }

    /**
     * Filter out system messages; Anthropic only accepts user/assistant in messages[].
     *
     * @param  ChatMessage[]  $messages
     * @return ChatMessage[]
     */
    private function filterUserMessages(array $messages): array
    {
        return array_values(
            array_filter($messages, fn (ChatMessage $m) => $m->role !== 'system')
        );
    }
}
