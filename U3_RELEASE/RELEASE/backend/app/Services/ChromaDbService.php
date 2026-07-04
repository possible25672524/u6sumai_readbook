<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client for ChromaDB REST API (chromadb/chroma Docker container).
 *
 * ChromaDB API base: http://chromadb:8000
 * Collection per document owner or global — we use ONE collection:
 *   "study_assistant_docs"
 *
 * Each vector in ChromaDB:
 *   id       = chunk.chroma_id (UUID)
 *   embedding = float[]
 *   metadata = { document_id, chunk_index, user_id, page_number }
 *   document  = chunk.content (stored for retrieval without MariaDB join)
 *
 * Required config:
 *   services.chromadb.url  — e.g. http://chromadb:8000
 *   services.chromadb.collection — default 'study_assistant_docs'
 */
class ChromaDbService
{
    private string $baseUrl;
    private string $collectionName;
    private ?string $collectionId = null;

    public function __construct()
    {
        $this->baseUrl        = rtrim(config('services.chromadb.url', 'http://chromadb:8000'), '/');
        $this->collectionName = config('services.chromadb.collection', 'study_assistant_docs');
    }

    // ─── Collection Management ────────────────────────────────────

    /**
     * Get or create the main collection. Returns the collection UUID.
     */
    public function getOrCreateCollection(): string
    {
        if ($this->collectionId) {
            return $this->collectionId;
        }

        // Try get first
        $response = Http::get("{$this->baseUrl}/api/v1/collections/{$this->collectionName}");

        if ($response->successful()) {
            $this->collectionId = $response->json('id');
            return $this->collectionId;
        }

        // Create
        $response = Http::post("{$this->baseUrl}/api/v1/collections", [
            'name'          => $this->collectionName,
            'metadata'      => ['hnsw:space' => 'cosine'],
            'get_or_create' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create ChromaDB collection: ' . $response->body());
        }

        $this->collectionId = $response->json('id');
        return $this->collectionId;
    }

    // ─── Upsert Embeddings ────────────────────────────────────────

    /**
     * Upsert vectors for a batch of chunks.
     *
     * @param  array<string, float[]> $vectors   [chroma_id => embedding]
     * @param  array<string, string>  $documents [chroma_id => text_content]
     * @param  array<string, array>   $metadatas [chroma_id => metadata_array]
     */
    public function upsert(array $vectors, array $documents, array $metadatas): void
    {
        $collectionId = $this->getOrCreateCollection();

        $ids        = array_keys($vectors);
        $embeddings = array_values($vectors);
        $docs       = array_map(fn($id) => $documents[$id] ?? '', $ids);
        $metas      = array_map(fn($id) => $metadatas[$id] ?? [], $ids);

        $response = Http::post(
            "{$this->baseUrl}/api/v1/collections/{$collectionId}/upsert",
            [
                'ids'        => $ids,
                'embeddings' => $embeddings,
                'documents'  => $docs,
                'metadatas'  => $metas,
            ]
        );

        if (!$response->successful()) {
            Log::error('ChromaDB upsert failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException('ChromaDB upsert failed: ' . $response->body());
        }
    }

    // ─── Query / Similarity Search ────────────────────────────────

    /**
     * Find the N most similar chunks to a query embedding.
     *
     * @param  float[]      $queryEmbedding
     * @param  int          $nResults
     * @param  array|null   $whereFilter    e.g. ['user_id' => 5]
     * @return array List of {id, document, metadata, distance}
     */
    public function query(
        array $queryEmbedding,
        int $nResults = 5,
        ?array $whereFilter = null
    ): array {
        $collectionId = $this->getOrCreateCollection();

        $body = [
            'query_embeddings' => [$queryEmbedding],
            'n_results'        => $nResults,
            'include'          => ['documents', 'metadatas', 'distances'],
        ];

        if ($whereFilter) {
            $body['where'] = $whereFilter;
        }

        $response = Http::post(
            "{$this->baseUrl}/api/v1/collections/{$collectionId}/query",
            $body
        );

        if (!$response->successful()) {
            throw new RuntimeException('ChromaDB query failed: ' . $response->body());
        }

        $data = $response->json();

        // Flatten the first (only) query result
        $ids       = $data['ids'][0]       ?? [];
        $documents = $data['documents'][0] ?? [];
        $metadatas = $data['metadatas'][0] ?? [];
        $distances = $data['distances'][0] ?? [];

        $results = [];
        foreach ($ids as $i => $id) {
            $results[] = [
                'id'       => $id,
                'document' => $documents[$i] ?? '',
                'metadata' => $metadatas[$i] ?? [],
                'distance' => $distances[$i] ?? 1.0,
            ];
        }

        return $results;
    }

    // ─── Delete ───────────────────────────────────────────────────

    /**
     * Delete vectors by their IDs (chroma_ids).
     *
     * @param string[] $ids
     */
    public function deleteByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $collectionId = $this->getOrCreateCollection();

        $response = Http::post(
            "{$this->baseUrl}/api/v1/collections/{$collectionId}/delete",
            ['ids' => $ids]
        );

        if (!$response->successful()) {
            Log::warning('ChromaDB delete failed', ['ids' => $ids, 'body' => $response->body()]);
        }
    }

    /**
     * Delete all vectors for a given document.
     */
    public function deleteByDocumentId(int $documentId): void
    {
        $collectionId = $this->getOrCreateCollection();

        $response = Http::post(
            "{$this->baseUrl}/api/v1/collections/{$collectionId}/delete",
            ['where' => ['document_id' => $documentId]]
        );

        if (!$response->successful()) {
            Log::warning('ChromaDB delete by document_id failed', [
                'document_id' => $documentId,
                'body'        => $response->body(),
            ]);
        }
    }
}
