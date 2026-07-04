# Changelog — AI Study Assistant Platform Backend

All notable backend changes are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.0.0] — 2026-06-27 — Phase 2: Document Upload & Processing Pipeline

### Added

#### Database
- `categories` table — hierarchical document categories with self-referential parent_id
- `documents` table — multi-source documents (PDF, DOCX, TXT, image, audio, video, YouTube, Google Drive) with SoftDeletes
- `document_categories` pivot table
- `processing_jobs` table — per-document pipeline step tracking with progress, attempts, error context
- `transcripts` table — Whisper API transcription results with segment timestamps
- `document_chunks` table — text chunks mapped to ChromaDB vectors via `chroma_id` UUID
- `notifications` table — Laravel database notification channel
- `failed_jobs` table — queue failure tracking

#### Models
- `Category` — self-referential tree, `HasFactory`, boot slug generation
- `Document` — `HasFactory`, `SoftDeletes`, source type/status/visibility constants, helper methods (`needsOcr()`, `needsTranscription()`, `markAsCompleted()` etc.), `file_path` hidden from serialization
- `DocumentChunk` — `HasFactory`, UUID scope guards, `notEmbedded()` scope
- `ProcessingJob` — `HasFactory`, `start()`/`complete()`/`fail()` state machine helpers, `canRetry()`
- `Transcript` — segments JSON cast, Whisper metadata

#### Controllers (18 new API endpoints)
- `DocumentController` — full CRUD + reprocess, status, chunks, transcript, download
- `CategoryController` — full CRUD with tree/paginated listing
- `ProcessingJobController` — per-document job listing, single job detail, admin system view

#### Services
- `DocumentStorageService` — MinIO upload, presigned URL generation, temp download
- `OcrService` — Tesseract OCR with Thai+English, PDF→image via pdftoppm, TSV confidence parsing
- `TranscriptionService` — OpenAI Whisper API with verbose_json + segment timestamps
- `EmbeddingService` — OpenAI `text-embedding-3-small` batch embedding, `embedChunks(Collection)` helper
- `ChromaDbService` — ChromaDB REST client: get-or-create collection, upsert, cosine similarity query, delete by ID/document
- `TextChunkerService` — 2000-char overlapping chunks with paragraph/sentence/word boundary detection, Thai text support

#### Queue Jobs
- `ProcessDocumentJob` — orchestrator, routes documents to correct pipeline branch
- `OcrDocumentJob` — Tesseract OCR + text chunking + bulk chunk insert (queue: `ocr`, timeout: 600s)
- `TranscribeAudioJob` — Whisper transcription + transcript record + chunking (queue: `transcribe`, timeout: 600s)
- `GenerateEmbeddingsJob` — batch embedding + ChromaDB upsert + progress tracking (queue: `embed`, timeout: 300s)

#### Form Requests
- `StoreDocumentRequest` — conditional file/URL validation per source type, 200MB limit, Thai error messages
- `UpdateDocumentRequest` — partial update with policy-gated authorize(), extracted_text correction triggers re-embedding
- `StoreCategoryRequest` — role-gated (admin/teacher only), unique slug validation

#### API Resources
- `DocumentResource`, `CategoryResource`, `ProcessingJobResource`, `DocumentChunkResource`, `TranscriptResource`, `UserResource`

#### Policies
- `DocumentPolicy` — viewAny, view (respects visibility), create, update, delete, reprocess, viewChunks
- `CategoryPolicy` — viewAny, view, create (admin/teacher), update (owner/admin), delete (owner if empty)

#### Events & Listeners
- `DocumentUploadedEvent` + listener slot (available for Phase 3 hooks)
- `DocumentProcessedEvent` → `SendDocumentProcessedNotification` listener
- `ProcessingFailedEvent` → `HandleProcessingFailed` listener

#### Notifications
- `DocumentProcessedNotification` — database channel (email channel wired, inactive)

#### Providers
- `EventServiceProvider` — event→listener registration
- `AppServiceProvider` — updated with `Gate::policy()` registrations for Document and Category

#### Configuration
- `config/services.php` — OpenAI, Anthropic, Tesseract, ChromaDB service config
- `config/filesystems.php` — MinIO S3-compatible disk, path-style endpoint
- `config/queue.php` — Redis queue driver with named queues

#### Tests
- 3 Unit test files: `TextChunkerServiceTest` (9 cases), `DocumentModelTest` (12 cases), `ProcessingJobModelTest` (4 cases)
- 3 Feature test files: `DocumentTest` (16 cases), `CategoryTest` (9 cases), `ProcessingJobTest` (4 cases) — 53 total
- `CreatesUsers` test helper trait
- 3 Model factories: `DocumentFactory`, `CategoryFactory`, `ProcessingJobFactory`

#### Routes
- 18 new authenticated API endpoints under `/api/documents`, `/api/categories`, `/api/jobs`, `/api/admin/jobs`

### Fixed (during acceptance)
- `chroma_id` UUID now generated manually in bulk inserts (bypasses Eloquent `boot()` events)
- `HasFactory` trait added to `Document`, `Category`, `ProcessingJob` models
- `UserResource` created (was referenced but missing)
- `ProcessDocumentJob::$documentId` changed to `public readonly` for test assertions
- `$chunkCount` scope bug in `TranscribeAudioJob` fixed via pass-by-reference
- `StoreCategoryRequest::authorize()` null-guard added
- `DocumentController::update()` preserves intentional null values via `array_intersect_key`
- `file_path` added to `Document::$hidden`
- `EventServiceProvider` registered in `bootstrap/app.php`
- `ProcessDocumentJob` sibling job imports added
- 7 additional minor import and redundancy fixes

---

## [1.0.0] — 2026-06-23 — Phase 1: Authentication & RBAC

### Added
- Laravel Sanctum Bearer token authentication
- Role/Permission system with many-to-many role↔permission
- 9 Auth endpoints (register, login, logout, me, forgot-password, reset-password, profile, password, admin ping)
- Role middleware `EnsureUserHasRole`
- Database seeders for roles, permissions, default accounts
- `users.is_active` field for account suspension (Phase 7)

---

## [0.1.0] — 2026-06-22 — Phase 0: Project Scaffold

### Added
- Docker Compose (Laravel, MariaDB, Redis, ChromaDB, MinIO, Nginx, queue-worker)
- Laravel 12 project structure via `docker-entrypoint.sh` bootstrap
- Environment configuration templates
- React + Vite + PWA frontend scaffold
