<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Services\AI\DTOs\EmbeddingResponse;

/**
 * Contract for vector-embedding providers.
 *
 * CRITICAL: All embeddings in the system MUST use the SAME model
 * (text-embedding-3-small) so that document chunks and query vectors
 * live in the same vector space for ChromaDB similarity search.
 *
 * Implementations: OpenAIEmbeddingProvider
 */
interface EmbeddingProviderInterface
{
    /**
     * Embed a single text string and return a float[] vector.
     *
     * @param  string  $text  Text to embed (≤ 8191 tokens for text-embedding-3-small)
     * @param  array<string, mixed>  $options
     * @return EmbeddingResponse
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException
     */
    public function embed(string $text, array $options = []): EmbeddingResponse;

    /**
     * Batch-embed multiple texts in a single API call (more efficient).
     *
     * @param  string[]  $texts
     * @param  array<string, mixed>  $options
     * @return EmbeddingResponse[]
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException
     */
    public function embedBatch(array $texts, array $options = []): array;

    /**
     * Return the embedding model identifier (e.g. "text-embedding-3-small").
     */
    public function getEmbeddingModel(): string;

    /**
     * Return the dimensionality of the embedding vectors.
     */
    public function getDimensions(): int;
}
