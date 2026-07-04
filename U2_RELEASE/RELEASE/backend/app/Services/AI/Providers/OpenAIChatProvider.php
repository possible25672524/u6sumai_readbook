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
 * OpenAI Chat Completions provider implementation.
 *
 * This provider is kept as an alternate / fallback for text generation.
 * Primary text generation in this project uses ClaudeProvider.
 *
 * @see https://platform.openai.com/docs/api-reference/chat
 */
final class OpenAIChatProvider implements AIProviderInterface
{
    use HasRetry, TracksUsage;

    private const API_BASE  = 'https://api.openai.com';
    private const PROVIDER  = 'openai';

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
        $model = $options['model'] ?? $this->model;

        $payload = [
            'model'      => $model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'messages'   => array_map(
                fn (ChatMessage $m) => $m->toOpenAIArray(),
                $messages
            ),
        ];

        foreach (['temperature', 'top_p', 'presence_penalty', 'frequency_penalty', 'stop'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        $context = "openai.chat.{$model}";

        $rawResponse = $this->withRetry(
            fn () => $this->makeRequest('/v1/chat/completions', $payload),
            context: $context,
            maxAttempts: $this->maxRetries,
        );

        $usage = AIUsage::fromOpenAI($rawResponse['usage'] ?? []);

        $this->recordUsage(
            provider:  self::PROVIDER,
            model:     $model,
            operation: $options['operation'] ?? 'chat',
            usage:     $usage,
            userId:    $options['user_id'] ?? null,
        );

        $choice     = $rawResponse['choices'][0] ?? [];
        $content    = $choice['message']['content'] ?? '';
        $stopReason = $this->normaliseStopReason($choice['finish_reason'] ?? 'stop');

        Log::debug("[OpenAIChatProvider] chat completed.", [
            'model'  => $model,
            'tokens' => $usage->totalTokens,
        ]);

        return new ChatResponse(
            content:     $content,
            provider:    self::PROVIDER,
            model:       $model,
            usage:       $usage,
            stopReason:  $stopReason,
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
            $this->makeRequest('/v1/chat/completions', [
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

    private function makeRequest(string $path, array $payload): array
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->post(self::API_BASE . $path, $payload);
        } catch (ConnectionException $e) {
            throw new AIProviderException(
                "Connection to OpenAI API failed: {$e->getMessage()}",
                provider: self::PROVIDER,
                previous: $e,
            );
        }

        return $this->parseResponse($response);
    }

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
                "OpenAI API error [{$response->status()}]: {$message}",
                provider:   self::PROVIDER,
                statusCode: $response->status(),
            );
        }

        return $response->json();
    }

    /**
     * Normalise OpenAI finish_reason to our unified stop_reason vocabulary.
     */
    private function normaliseStopReason(string $finishReason): string
    {
        return match ($finishReason) {
            'stop'        => 'end_turn',
            'length'      => 'max_tokens',
            'content_filter' => 'content_filter',
            default       => $finishReason,
        };
    }
}
