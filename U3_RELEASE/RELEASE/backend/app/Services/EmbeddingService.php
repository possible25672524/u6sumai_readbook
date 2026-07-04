<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generates text embeddings using OpenAI text-embedding-3-small.
 *
 * IMPORTANT: All embeddings in the system MUST use this same model
 * so that vector spaces are comparable in ChromaDB.
 *
 * Required config:
 *   services.openai.key
 *   services.openai.embedding_model — default 'text-embedding-3-small'
 */
class EmbeddingService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    // OpenAI limit: 2048 inputs per batch
    private const BATCH_SIZE = 100;

    // Dimension for text-embedding-3-small
    public const DIMENSIONS = 1536;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
        $this->model  = config('services.openai.embedding_model', 'text-embedding-3-small');
        $this->apiUrl = 'https://api.openai.com/v1/embeddings';
    }

    /**
     * Embed a single text string.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        $results = $this->embedBatch([$text]);

        return $results[0] ?? throw new RuntimeException('Empty embedding response');
    }

    /**
     * Embed multiple texts in batches.
     *
     * @param  string[] $texts
     * @return float[][] — same order as input
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $batches = array_chunk($texts, self::BATCH_SIZE);
        $results = [];

        foreach ($batches as $batch) {
            $batchResults = $this->callApi($batch);
            $results      = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Embed a collection of DocumentChunk models and return
     * an array of [chroma_id => embedding_vector].
     *
     * @param  \Illuminate\Database\Eloquent\Collection $chunks
     * @return array<string, float[]>
     */
    public function embedChunks(Collection $chunks): array
    {
        $texts    = $chunks->pluck('content')->toArray();
        $vectors  = $this->embedBatch($texts);
        $chromaIds = $chunks->pluck('chroma_id')->toArray();

        $result = [];
        foreach ($chromaIds as $i => $chromaId) {
            if (isset($vectors[$i])) {
                $result[$chromaId] = $vectors[$i];
            }
        }

        return $result;
    }

    // ─── Private ──────────────────────────────────────────────────

    /**
     * @param  string[] $texts
     * @return float[][]
     */
    private function callApi(array $texts): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])->post($this->apiUrl, [
            'model' => $this->model,
            'input' => $texts,
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI Embedding API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException(
                'Embedding API failed (' . $response->status() . '): ' . $response->body()
            );
        }

        $data = $response->json();

        // Sort by index to guarantee order
        $embeddings = collect($data['data'] ?? [])
            ->sortBy('index')
            ->pluck('embedding')
            ->toArray();

        return $embeddings;
    }
}
