# U3 Backend Release — Phase 2: Document Upload & Processing Pipeline

**Team:** U3 — Backend Lead
**Phase:** 2
**Release Date:** 2026-06-27
**Status:** ✅ Accepted — Ready for Merge

---

## Overview

This release implements the complete **Document Upload and Processing Pipeline** for the AI Study Assistant Platform. It covers all backend components required to upload learning materials, extract text via OCR or Whisper transcription, generate embeddings, and store vectors in ChromaDB — ready for RAG in Phase 5.

---

## What's Included

### Source Code (`backend/`)
- **5 Eloquent Models** with relationships, scopes, and HasFactory
- **3 API Controllers** covering 18 REST endpoints
- **6 Services** (storage, OCR, transcription, embedding, ChromaDB, text chunking)
- **4 Queue Jobs** (orchestrator + OCR + transcription + embedding)
- **3 Form Requests** with Thai-language validation messages
- **6 API Resources** (JSON transformers)
- **2 Policies** with full CRUD authorization
- **2 Providers** (AppServiceProvider + EventServiceProvider)
- **3 Events + 2 Listeners + 1 Notification**
- **3 Config files** (services, filesystems, queue)
- **bootstrap/app.php** with middleware aliases and provider registration

### Database (`database/`)
- **8 Migrations** (categories → documents → chunks → processing_jobs → transcripts → pivot → notifications → failed_jobs)
- **3 Factories** (Document, Category, ProcessingJob)

### Routes (`routes/`)
- **api.php** — Phase 1 + Phase 2 combined route file (18 new endpoints)

### Tests (`tests/`)
- **3 Unit test files** — 25 test cases
- **3 Feature test files** — 28 test cases
- **CreatesUsers** test helper trait

### Documentation (`docs/`)
- `API_PHASE2.md` — full endpoint specification
- `QUEUE_ARCHITECTURE.md` — queue job pipeline diagram

---

## Installation

### Prerequisites
These must exist from Phase 1:
- Laravel 12 skeleton with `artisan`, `composer.json`, `public/index.php`
- Phase 1 migrations (roles, permissions, users, personal_access_tokens)
- Phase 1 Auth controllers (`AuthController`, `PasswordResetController`, `ProfileController`)
- Phase 1 `PingController` in `App\Http\Controllers\Api\Admin\`
- Phase 1 `EnsureUserHasRole` middleware
- `User` model with `role()` relation and `is_active` field
- Phase 1 `UserFactory`

### Step 1 — Merge source files
Copy all files from this release into the Laravel project root, preserving directory structure.

### Step 2 — Merge `bootstrap/app.php`
The provided `bootstrap/app.php` registers Phase 2 providers. If Phase 1 has its own version, **merge** the `withProviders()` array — do not replace the file entirely.

```php
->withProviders([
    \App\Providers\AppServiceProvider::class,
    \App\Providers\EventServiceProvider::class,  // ADD THIS
])
```

### Step 3 — Set environment variables
Add to `backend/.env`:
```env
# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_WHISPER_MODEL=whisper-1

# Anthropic
ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-4-5

# ChromaDB
CHROMA_URL=http://chromadb:8000
CHROMA_COLLECTION=study_assistant_docs

# MinIO
MINIO_ACCESS_KEY=minio_admin
MINIO_SECRET_KEY=minio_secret
MINIO_BUCKET=study-assistant-files
MINIO_ENDPOINT=http://minio:9000
DOCUMENT_STORAGE_DISK=s3

# Tesseract
TESSERACT_BIN=/usr/bin/tesseract
TESSERACT_LANGUAGES=tha+eng
```

### Step 4 — Run migrations
```bash
docker compose exec backend php artisan migrate
```

### Step 5 — Update queue worker
In `docker-compose.yml`, update the `queue-worker` command:
```yaml
command: ["php", "artisan", "queue:work",
          "--queue=default,ocr,transcribe,embed",
          "--tries=3", "--timeout=600"]
```

### Step 6 — Run tests
```bash
docker compose exec backend php artisan test --filter=Document
docker compose exec backend php artisan test --filter=Category
docker compose exec backend php artisan test --filter=ProcessingJob
docker compose exec backend php artisan test tests/Unit/
```

---

## Architecture Notes

### Processing Pipeline
```
POST /api/documents
        │
        ▼ (queue: default)
ProcessDocumentJob
        │
   ┌────┴────┐
   ▼         ▼
OcrDocumentJob    TranscribeAudioJob
(queue: ocr)      (queue: transcribe)
   │              │
   └──────┬───────┘
          ▼ (queue: embed)
   GenerateEmbeddingsJob
          │
    ┌─────┴─────┐
    ▼           ▼
 ChromaDB    MariaDB
 (vectors)   (chunks)
```

### Critical Implementation Notes
1. **`DocumentChunk` bulk inserts bypass Eloquent model events** — `chroma_id` UUID is generated manually in the row array, not via `boot()`.
2. **All embeddings use `text-embedding-3-small`** — mixing models breaks ChromaDB vector space comparability.
3. **`file_path` is hidden from all API responses** — use `GET /api/documents/{id}/download` for presigned URLs.

---

## Known Limitations
- Google Drive / YouTube URL documents are stored but not yet fetched/processed (Phase 3).
- `AIProviderInterface` Strategy Pattern deferred to Phase 3.
- Rate limiting on AI endpoints deferred to Phase 8.
- OCR accuracy on low-quality Thai scans is untested against real documents.
