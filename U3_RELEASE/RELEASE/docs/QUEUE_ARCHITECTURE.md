# Queue Architecture — Phase 2 Processing Pipeline

## Overview

All document processing is fully asynchronous. No AI/OCR/transcription work is performed
inside HTTP request/response cycles. All heavy tasks are dispatched to named Redis queues
and handled by dedicated queue workers.

---

## Queue Names and Priority

Workers must be started with all four queue names, in priority order:

```bash
php artisan queue:work \
  --queue=default,ocr,transcribe,embed \
  --tries=3 \
  --timeout=600
```

| Queue | Priority | Jobs | Max Timeout |
|---|---|---|---|
| `default` | 1 (highest) | ProcessDocumentJob, notifications, listeners | 60s |
| `ocr` | 2 | OcrDocumentJob | 600s |
| `transcribe` | 3 | TranscribeAudioJob | 600s |
| `embed` | 4 | GenerateEmbeddingsJob | 300s |

---

## Pipeline Flow

### Text Documents (PDF / Image)

```
HTTP POST /api/documents
        │
        ▼ [queue: default, tries: 1]
ProcessDocumentJob
  ├─ document.markAsProcessing()
  ├─ creates ProcessingJob(type=ocr)
  └─ OcrDocumentJob::withChain([
          GenerateEmbeddingsJob($docId)
     ])->dispatch($docId, $jobId)
             │
             ▼ [queue: ocr, tries: 3, timeout: 600s]
        OcrDocumentJob
          ├─ DocumentStorageService::downloadToTemp()
          ├─ OcrService::extract(path, mimeType)
          │    └─ pdftoppm (PDF→PNG pages)
          │    └─ tesseract (PNG→text + confidence TSV)
          ├─ document.update(extracted_text, page_count)
          ├─ TextChunkerService::chunk(text)
          ├─ DocumentChunk::insert(rows with chroma_id UUIDs)
          ├─ processingJob.complete(meta)
          └─ [chain continues to GenerateEmbeddingsJob]
                       │
                       ▼ [queue: embed, tries: 3, timeout: 300s]
                GenerateEmbeddingsJob
                  ├─ loads chunks where is_embedded=false
                  ├─ EmbeddingService::embedChunks() [batches of 50]
                  │    └─ OpenAI /v1/embeddings API
                  ├─ ChromaDbService::upsert(vectors, docs, metadata)
                  ├─ DocumentChunk::update(is_embedded=true)
                  ├─ document.markAsCompleted()
                  └─ event(DocumentProcessedEvent)
                               │
                               ▼
                    SendDocumentProcessedNotification
                      └─ user.notify(DocumentProcessedNotification)
                           └─ stored in notifications table
```

### Audio / Video Documents

```
ProcessDocumentJob
  └─ TranscribeAudioJob::withChain([
          GenerateEmbeddingsJob($docId)
     ])->dispatch($docId, $jobId)
             │
             ▼ [queue: transcribe, tries: 3, timeout: 600s]
        TranscribeAudioJob
          ├─ DocumentStorageService::downloadToTemp()
          ├─ TranscriptionService::transcribe(path, language)
          │    └─ OpenAI /v1/audio/transcriptions (verbose_json)
          ├─ Transcript::updateOrCreate(content, segments, duration)
          ├─ document.update(extracted_text, duration_seconds)
          ├─ TextChunkerService::chunk(text)
          ├─ DocumentChunk::insert(rows with chroma_id UUIDs)
          ├─ processingJob.complete(meta)
          └─ [chain continues to GenerateEmbeddingsJob]
                       │ (same as above)
```

### Plain Text / URL Documents

```
ProcessDocumentJob
  ├─ [TXT: text already extracted in controller]
  ├─ creates ProcessingJob(type=embed)
  └─ GenerateEmbeddingsJob::dispatch($docId, $jobId)
             │ (goes straight to embedding)
```

---

## Job Chaining

`OcrDocumentJob` and `TranscribeAudioJob` use `::withChain()` to automatically
dispatch `GenerateEmbeddingsJob` upon their successful completion.

If the first job fails permanently (exhausts `tries`), the chained job is
**not dispatched**. The document status remains `failed` and the user is notified.

---

## Error Handling

Each job has a `failed(Throwable $e)` method called after all retries are exhausted:
- `processingJob.fail(message)` — records error in DB
- `document.markAsFailed()` — updates document status
- `ProcessingFailedEvent` is fired by `ProcessDocumentJob` orchestrator if setup fails

### Retry Strategy

| Scenario | Behaviour |
|---|---|
| Tesseract crash | OcrDocumentJob retried up to 3× with exponential backoff |
| Whisper rate limit (429) | TranscribeAudioJob retried up to 3× |
| OpenAI embedding error | GenerateEmbeddingsJob retried up to 3× |
| ChromaDB unavailable | GenerateEmbeddingsJob retried up to 3× |
| MinIO unavailable | Job fails immediately (file not found) |

Failed jobs are logged to `failed_jobs` table and visible at `GET /api/admin/jobs`.

---

## Critical Implementation Notes

### 1. Manual `chroma_id` on Bulk Insert

```php
// WRONG — chroma_id will be NULL (boot() events skipped):
DocumentChunk::insert([['document_id' => 1, 'content' => '...']]);

// CORRECT — UUID generated per row:
DocumentChunk::insert([
    ['document_id' => 1, 'content' => '...', 'chroma_id' => (string) Str::uuid()]
]);
```

### 2. Consistent Embedding Model

All embeddings — both at index time (chunks) and query time (RAG search in Phase 5) —
**must** use `text-embedding-3-small`. Mixing models produces incomparable vectors.

### 3. ChromaDB Metadata Schema

Each ChromaDB vector has this metadata structure (used for filtering in Phase 5 RAG):

```json
{
  "document_id": 5,
  "user_id": 3,
  "chunk_index": 12,
  "page_number": 4,
  "source_type": "pdf"
}
```

Query filter example (Phase 5 will use this):
```php
$chroma->query($queryEmbedding, nResults: 5, whereFilter: ['user_id' => $userId]);
```

---

## docker-compose.yml Queue Worker Update Required

The current `docker-compose.yml` queue-worker service does not include the named queues.
**U1 Infrastructure must update this before Phase 2 goes live:**

```yaml
# Current (incorrect for Phase 2):
command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=600"]

# Required:
command: ["php", "artisan", "queue:work",
          "--queue=default,ocr,transcribe,embed",
          "--tries=3", "--timeout=600"]
```

Without this change, OCR and transcription jobs will only run on the `default` queue,
competing with lightweight notification jobs for the same worker capacity.
