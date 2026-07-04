<?php
/**
 * INTEGRATION PATCH-02
 * Defect:    DEFECT-06 — ChromaDB /api/v1 endpoints used; image 1.5.7 requires /api/v2
 * Severity:  FATAL
 * Root Cause: U3 ChromaDbService was authored against ChromaDB 0.4/0.5 (v1 REST API).
 *             U1 pins chromadb/chroma:1.5.7 which ships ONLY /api/v2.
 *             Every HTTP call in ChromaDbService will receive 404 at runtime.
 * Files Modified: backend/app/Services/ChromaDbService.php
 * Risk: Low — pure URL string replacement, no logic change, no interface change.
 */

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChromaDbService
{
    private string $baseUrl;
    private string $collectionName;
    private ?string $collectionId = null;

    // PATCH-02: API version constant changed from v1 to v2
    private const API_VERSION = 'api/v2';

    public function __construct()
    {
        $this->baseUrl        = rtrim(config('services.chromadb.url', 'http://chromadb:8000'), '/');
        $this->collectionName = config('services.chromadb.collection', 'study_assistant_docs');
    }

    public function getOrCreateCollection(): string
    {
        if ($this->collectionId) {
            return $this->collectionId;
        }

        // PATCH-02: /api/v1/collections -> /api/v2/collections
        $response = Http::get("{$this->baseUrl}/" . self::API_VERSION . "/collections/{$this->collectionName}");

        if ($response->successful()) {
            $this->collectionId = $response->json('id');
            return $this->collectionId;
        }

        // PATCH-02: /api/v1/collections -> /api/v2/collections
        $response = Http::post("{$this->baseUrl}/" . self::API_VERSION . "/collections", [
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

    public function upsert(array $vectors, array $documents, array $metadatas): void
    {
        $collectionId = $this->getOrCreateCollection();

        $ids        = array_keys($vectors);
        $embeddings = array_values($vectors);
        $docs       = array_map(fn($id) => $documents[$id] ?? '', $ids);
        $metas      = array_map(fn($id) => $metadatas[$id] ?? [], $ids);

        // PATCH-02: /api/v1/collections/{id}/upsert -> /api/v2/collections/{id}/upsert
        $response = Http::post(
            "{$this->baseUrl}/" . self::API_VERSION . "/collections/{$collectionId}/upsert",
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

        // PATCH-02: /api/v1/collections/{id}/query -> /api/v2/collections/{id}/query
        $response = Http::post(
            "{$this->baseUrl}/" . self::API_VERSION . "/collections/{$collectionId}/query",
            $body
        );

        if (!$response->successful()) {
            throw new RuntimeException('ChromaDB query failed: ' . $response->body());
        }

        $data = $response->json();

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

    public function deleteByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $collectionId = $this->getOrCreateCollection();

        // PATCH-02: /api/v1/ -> /api/v2/
        $response = Http::post(
            "{$this->baseUrl}/" . self::API_VERSION . "/collections/{$collectionId}/delete",
            ['ids' => $ids]
        );

        if (!$response->successful()) {
            Log::warning('ChromaDB delete failed', ['ids' => $ids, 'body' => $response->body()]);
        }
    }

    public function deleteByDocumentId(int $documentId): void
    {
        $collectionId = $this->getOrCreateCollection();

        // PATCH-02: /api/v1/ -> /api/v2/
        $response = Http::post(
            "{$this->baseUrl}/" . self::API_VERSION . "/collections/{$collectionId}/delete",
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
