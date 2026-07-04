<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\EmbeddingProviderInterface;
use App\Services\AI\Concerns\HasRetry;
use App\Services\AI\Concerns\TracksUsage;
use App\Services\AI\DTOs\AIUsage;
use App\Services\AI\DTOs\EmbeddingResponse;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Embeddings provider (text-embedding-3-small).
 *
 * IMPORTANT: This is the ONLY embedding model used in this system.
 * Never swap to a different model without re-embedding ALL document chunks,
 * because vectors from different models are not comparable in ChromaDB.
 *
 * @see https://platform.openai.com/docs/api-reference/embeddings
 */
final class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    use HasRetry, TracksUsage;

    private const API_BASE   = 'https://api.openai.com';
    private const PROVIDER   = 'openai';
    private const DIMENSIONS = 1536;      // text-embedding-3-small dimensionality
    private const MAX_BATCH  = 2048;      // OpenAI batch limit (input items)

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int    $timeoutSeconds,
        private readonly int    $maxRetries,
    ) {}

    // ── EmbeddingProviderInterface ────────────────────────────────────────

    public function embed(string $text, array $options = []): EmbeddingResponse
    {
        $responses = $this->embedBatch([$text], $options);

        return $responses[0];
    }

    public function embedBatch(array $texts, array $options = []): array
    {
        if (empty($texts)) {
            return [];
        }

        if (count($texts) > self::MAX_BATCH) {
            throw new \InvalidArgumentException(
                "Batch size " . count($texts) . " exceeds maximum " . self::MAX_BATCH
            );
        }

        $model   = $options['model'] ?? $this->model;
        $payload = [
            'model'           => $model,
            'input'           => $texts,
            'encoding_format' => 'float',
        ];

        $rawResponse = $this->withRetry(
            fn () => $this->makeRequest('/v1/embeddings', $payload),
            context: "openai.embed.{$model}",
            maxAttempts: $this->maxRetries,
        );

        $usage = AIUsage::fromOpenAI($rawResponse['usage'] ?? []);

        $this->recordUsage(
            provider:  self::PROVIDER,
            model:     $model,
            operation: 'embed',
            usage:     $usage,
            userId:    $options['user_id'] ?? null,
        );

        Log::debug("[OpenAIEmbeddingProvider] batch embedded.", [
            'count'  => count($texts),
            'tokens' => $usage->totalTokens,
        ]);

        return array_map(
            fn (array $item, string $text) => new EmbeddingResponse(
                vector:     $item['embedding'],
                inputText:  $text,
                model:      $model,
                tokenCount: (int) round($usage->promptTokens / max(count($texts), 1)),
                dimensions: count($item['embedding']),
            ),
            $rawResponse['data'],
            $texts
        );
    }

    public function getEmbeddingModel(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return self::DIMENSIONS;
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
                "Connection to OpenAI Embeddings API failed: {$e->getMessage()}",
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
                "OpenAI Embeddings error [{$response->status()}]: {$message}",
                provider:   self::PROVIDER,
                statusCode: $response->status(),
            );
        }

        return $response->json();
    }
}
