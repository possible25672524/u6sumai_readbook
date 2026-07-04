<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Normalised response from EmbeddingProviderInterface::embed().
 */
final class EmbeddingResponse
{
    public function __construct(
        /** The raw float vector */
        public readonly array $vector,
        /** The original input text that was embedded */
        public readonly string $inputText,
        /** Model that generated the embedding */
        public readonly string $model,
        /** Token count of the input */
        public readonly int $tokenCount,
        /** Vector dimensionality */
        public readonly int $dimensions,
    ) {}

    /**
     * Return the L2 norm of the vector (useful for sanity checks).
     */
    public function norm(): float
    {
        $sum = 0.0;
        foreach ($this->vector as $v) {
            $sum += $v * $v;
        }

        return sqrt($sum);
    }

    /**
     * Cosine similarity with another embedding vector.
     * Both vectors must have the same dimensionality.
     *
     * @param  float[]  $other
     */
    public function cosineSimilarity(array $other): float
    {
        if (count($other) !== $this->dimensions) {
            throw new \InvalidArgumentException(
                "Dimension mismatch: expected {$this->dimensions}, got " . count($other)
            );
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($this->vector as $i => $v) {
            $dot   += $v * $other[$i];
            $normA += $v * $v;
            $normB += $other[$i] * $other[$i];
        }

        $denom = sqrt($normA) * sqrt($normB);

        return $denom > 0 ? $dot / $denom : 0.0;
    }
}
