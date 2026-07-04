<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\EmbeddingResponse;

/**
 * High-level embedding service used by the Document Processing Pipeline (Module 4)
 * and the RAG Chatbot (Module 9).
 *
 * All text chunking and ChromaDB interactions are handled here.
 */
final class EmbeddingService
{
    /**
     * Default chunk size in characters (≈ 400 tokens for typical Thai/English text).
     * Overlap ensures context continuity between chunks.
     */
    private const CHUNK_SIZE    = 1500;
    private const CHUNK_OVERLAP = 200;

    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Embed a single query string for RAG retrieval.
     * The query vector is compared against stored document chunk vectors in ChromaDB.
     */
    public function embedQuery(string $query, ?int $userId = null): EmbeddingResponse
    {
        return $this->ai->embed($query, ['user_id' => $userId]);
    }

    /**
     * Chunk a long document text and return embeddings for each chunk.
     *
     * @param  string  $text        Full document text (post-OCR or post-transcription)
     * @param  string  $documentId  Used as metadata for ChromaDB filtering
     * @param  int|null $userId
     * @return array{chunk: string, embedding: float[], index: int, char_start: int, char_end: int}[]
     */
    public function embedDocument(
        string $text,
        string $documentId,
        ?int $userId = null,
    ): array {
        $chunks = $this->chunkText($text);

        if (empty($chunks)) {
            return [];
        }

        $chunkTexts   = array_column($chunks, 'text');
        $embeddings   = $this->ai->embedBatch($chunkTexts, ['user_id' => $userId]);

        return array_map(
            fn (array $chunk, EmbeddingResponse $embedding, int $idx) => [
                'chunk'       => $chunk['text'],
                'embedding'   => $embedding->vector,
                'index'       => $idx,
                'char_start'  => $chunk['char_start'],
                'char_end'    => $chunk['char_end'],
                'document_id' => $documentId,
                'model'       => $embedding->model,
                'dimensions'  => $embedding->dimensions,
            ],
            $chunks,
            $embeddings,
            array_keys($chunks),
        );
    }

    /**
     * Return embedding model metadata for validation / logging.
     */
    public function getModelInfo(): array
    {
        return [
            'model'      => $this->ai->getEmbeddingModel(),
            'dimensions' => $this->ai->getEmbeddingDimensions(),
        ];
    }

    // ── Text Chunking ─────────────────────────────────────────────────────

    /**
     * Split text into overlapping chunks that fit within the embedding model's
     * token budget while preserving sentence boundaries where possible.
     *
     * @return array{text: string, char_start: int, char_end: int}[]
     */
    private function chunkText(string $text): array
    {
        $text = $this->normaliseText($text);

        if (strlen($text) <= self::CHUNK_SIZE) {
            return [['text' => $text, 'char_start' => 0, 'char_end' => strlen($text)]];
        }

        $chunks    = [];
        $start     = 0;
        $textLen   = strlen($text);

        while ($start < $textLen) {
            $end = min($start + self::CHUNK_SIZE, $textLen);

            // Try to break at a sentence boundary (. ! ?) rather than mid-sentence
            if ($end < $textLen) {
                $boundary = $this->findSentenceBoundary($text, $end);
                if ($boundary > $start) {
                    $end = $boundary;
                }
            }

            $chunkText = trim(substr($text, $start, $end - $start));

            if ($chunkText !== '') {
                $chunks[] = [
                    'text'       => $chunkText,
                    'char_start' => $start,
                    'char_end'   => $end,
                ];
            }

            // Move forward with overlap
            $start = max($start + 1, $end - self::CHUNK_OVERLAP);
        }

        return $chunks;
    }

    private function findSentenceBoundary(string $text, int $near): int
    {
        // Look backwards from $near for a sentence-ending punctuation
        $window = 200;
        $search = substr($text, max(0, $near - $window), $window);

        // Try: Thai sentence end (ๆ, ฯ), period, exclamation, question mark
        if (preg_match('/[.!?ๆฯ]\s+/u', $search, $m, PREG_OFFSET_CAPTURE)) {
            $lastMatch = null;
            preg_match_all('/[.!?ๆฯ]\s+/u', $search, $matches, PREG_OFFSET_CAPTURE);
            if (! empty($matches[0])) {
                $lastMatch = end($matches[0]);
                return max(0, $near - $window) + $lastMatch[1] + mb_strlen($lastMatch[0], '8bit');
            }
        }

        return $near;
    }

    private function normaliseText(string $text): string
    {
        // Collapse multiple whitespace / newlines
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
