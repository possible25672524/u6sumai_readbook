<?php
/**
 * INTEGRATION PATCH-03
 * Defect:    DEFECT-02 — EmbeddingService.php file collision between U2 and U3
 * Severity:  FATAL
 * Root Cause: U2 delivered App\Services\EmbeddingService (AIManager-based, returns structured arrays).
 *             U3 delivered App\Services\EmbeddingService (direct HTTP, returns float[][]).
 *             Both at the same path. U3's GenerateEmbeddingsJob calls embedChunks(Collection)
 *             which does not exist in U2's version.
 *
 * Resolution Strategy: MERGE — do NOT redesign.
 *   - Keep U2's EmbeddingService as the canonical implementation (AIManager-based, correct abstraction).
 *   - Add embedChunks(Collection $chunks) method to U2's service so U3's Job code works unchanged.
 *   - embedChunks() is a thin adapter: maps Collection of DocumentChunk models -> batch embed -> [chroma_id => vector[]]
 *   - This preserves U2's architecture (AIManager abstraction) while satisfying U3's Job contract.
 *
 * Files Modified: backend/app/Services/EmbeddingService.php (U2 base + adapter method added)
 * Risk: Low — adds one method; does not modify existing U2 methods.
 * Validation: U3 GenerateEmbeddingsJob calls $embedder->embedChunks($batch) -> resolved.
 *             U2 AIServiceProvider singleton binding still works.
 */

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\EmbeddingResponse;
use Illuminate\Database\Eloquent\Collection;

/**
 * High-level embedding service — Document Processing Pipeline (Module 4) + RAG (Module 9).
 *
 * Merged by U5 Integration:
 *   - Base implementation from U2 (AIManager-based, Strategy Pattern)
 *   - embedChunks() adapter method added to satisfy U3 GenerateEmbeddingsJob contract
 */
final class EmbeddingService
{
    private const CHUNK_SIZE    = 1500;
    private const CHUNK_OVERLAP = 200;

    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Embed a single query string for RAG retrieval.
     */
    public function embedQuery(string $query, ?int $userId = null): EmbeddingResponse
    {
        return $this->ai->embed($query, ['user_id' => $userId]);
    }

    /**
     * Chunk a long document text and return embeddings for each chunk.
     * Used by U2 high-level pipeline patterns.
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

        $chunkTexts = array_column($chunks, 'text');
        $embeddings = $this->ai->embedBatch($chunkTexts, ['user_id' => $userId]);

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
     * PATCH-03 ADDITION: embedChunks() adapter for U3 GenerateEmbeddingsJob.
     *
     * Accepts a Collection of DocumentChunk Eloquent models,
     * batch-embeds their content via AIManager (preserving U2 abstraction),
     * and returns [chroma_id => float[]] as U3's Job expects.
     *
     * @param  Collection  $chunks  Collection of App\Models\DocumentChunk
     * @return array<string, float[]>  [chroma_id => embedding_vector]
     */
    public function embedChunks(Collection $chunks): array
    {
        if ($chunks->isEmpty()) {
            return [];
        }

        $texts     = $chunks->pluck('content')->toArray();
        $chromaIds = $chunks->pluck('chroma_id')->toArray();

        // Delegate to AIManager -> OpenAIEmbeddingProvider (U2 Strategy Pattern preserved)
        $responses = $this->ai->embedBatch($texts);

        $result = [];
        foreach ($chromaIds as $i => $chromaId) {
            if (isset($responses[$i])) {
                $result[$chromaId] = $responses[$i]->vector;
            }
        }

        return $result;
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

    // ── Text Chunking (U2 implementation preserved) ──────────────────────

    private function chunkText(string $text): array
    {
        $text = $this->normaliseText($text);

        if (strlen($text) <= self::CHUNK_SIZE) {
            return [['text' => $text, 'char_start' => 0, 'char_end' => strlen($text)]];
        }

        $chunks  = [];
        $start   = 0;
        $textLen = strlen($text);

        while ($start < $textLen) {
            $end = min($start + self::CHUNK_SIZE, $textLen);

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

            $start = max($start + 1, $end - self::CHUNK_OVERLAP);
        }

        return $chunks;
    }

    private function findSentenceBoundary(string $text, int $near): int
    {
        $window = 200;
        $search = substr($text, max(0, $near - $window), $window);

        if (preg_match('/[.!?ๆฯ]\s+/u', $search, $m, PREG_OFFSET_CAPTURE)) {
            preg_match_all('/[.!?ๆฯ]\s+/u', $search, $matches, PREG_OFFSET_CAPTURE);
            if (!empty($matches[0])) {
                $lastMatch = end($matches[0]);
                return max(0, $near - $window) + $lastMatch[1] + mb_strlen($lastMatch[0], '8bit');
            }
        }

        return $near;
    }

    private function normaliseText(string $text): string
    {
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
