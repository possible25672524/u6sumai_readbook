# U3 Implementation Report — Phase 2

**Team:** U3 — Backend Lead
**Phase:** 2 — Document Upload & Processing Pipeline
**Implementation Period:** 2026-06-23 → 2026-06-27
**Final Status:** ✅ Complete

---

## 1. Scope Summary

Phase 2 adds the document ingestion layer that is a prerequisite for all remaining feature modules (3–14). No AI feature (summarisation, quiz generation, chatbot) can work without the processed document chunks and ChromaDB vectors this phase produces.

**In scope:**
- Multi-format document upload (PDF, DOCX, TXT, image, audio, video, YouTube URL, Google Drive URL)
- Async processing pipeline (OCR → Transcription → Embedding)
- ChromaDB vector storage
- Document CRUD with category tagging and visibility control
- Processing job tracking with progress reporting
- User notifications on pipeline completion/failure

**Deferred to later phases:**
- AI summarisation (Phase 3)
- Quiz generation (Phase 4)
- RAG chatbot (Phase 5)
- Google Drive / YouTube URL fetching (Phase 3)
- `AIProviderInterface` Strategy Pattern (Phase 3)
- Rate limiting on AI endpoints (Phase 8)

---

## 2. Files Delivered

### Migrations (8)
| File | Table | Notes |
|---|---|---|
| `2026_06_23_000010_create_categories_table.php` | `categories` | Self-referential, FK to users |
| `2026_06_23_000011_create_documents_table.php` | `documents` | SoftDeletes, 9 source types |
| `2026_06_23_000012_create_document_categories_table.php` | `document_categories` | Composite PK |
| `2026_06_23_000013_create_processing_jobs_table.php` | `processing_jobs` | State machine, progress % |
| `2026_06_23_000014_create_transcripts_table.php` | `transcripts` | 1:1 with documents |
| `2026_06_23_000015_create_document_chunks_table.php` | `document_chunks` | chroma_id UUID, is_embedded |
| `2026_06_23_000016_create_notifications_table.php` | `notifications` | Laravel morphs |
| `2026_06_23_000017_create_failed_jobs_table.php` | `failed_jobs` | Laravel standard |

### Models (5)
| Class | Traits | Key Features |
|---|---|---|
| `Category` | `HasFactory` | Self-referential parent/children, boot slug |
| `Document` | `HasFactory`, `SoftDeletes` | 15 constants, 7 helpers, file_path hidden |
| `DocumentChunk` | `HasFactory` | notEmbedded() scope, UUID boot (for non-bulk) |
| `ProcessingJob` | `HasFactory` | start/complete/fail state machine |
| `Transcript` | — | segments JSON, Whisper metadata |

### Controllers (3) — 18 endpoints total
| Class | Methods | Endpoints |
|---|---|---|
| `DocumentController` | 10 | GET/POST/PUT/DELETE /documents + 6 sub-routes |
| `CategoryController` | 5 | Full CRUD via apiResource |
| `ProcessingJobController` | 3 | Per-doc jobs, single job, admin listing |

### Services (6)
| Class | External Dependency | Key Responsibility |
|---|---|---|
| `DocumentStorageService` | MinIO via `Storage::disk('s3')` | Upload, presign, temp download |
| `OcrService` | Tesseract binary, pdftoppm | PDF/image → text + confidence |
| `TranscriptionService` | OpenAI Whisper API | Audio/video → text + segments |
| `EmbeddingService` | OpenAI Embeddings API | Text → float[1536] vectors |
| `ChromaDbService` | ChromaDB REST API | Vector upsert, cosine query, delete |
| `TextChunkerService` | None | 2000-char overlapping Thai/EN chunks |

### Queue Jobs (4)
| Class | Queue | Timeout | Tries | Chains to |
|---|---|---|---|---|
| `ProcessDocumentJob` | default | 60s | 1 | OcrDocumentJob OR TranscribeAudioJob OR GenerateEmbeddingsJob |
| `OcrDocumentJob` | ocr | 600s | 3 | GenerateEmbeddingsJob (via withChain) |
| `TranscribeAudioJob` | transcribe | 600s | 3 | GenerateEmbeddingsJob (via withChain) |
| `GenerateEmbeddingsJob` | embed | 300s | 3 | — (fires DocumentProcessedEvent) |

### Events → Listeners → Notifications
```
DocumentUploadedEvent        → [no listener yet — Phase 3 hook]
DocumentProcessedEvent       → SendDocumentProcessedNotification
                                 → DocumentProcessedNotification (database channel)
ProcessingFailedEvent        → HandleProcessingFailed
                                 → DocumentProcessedNotification (database channel, failure message)
```

### Form Requests (3)
| Class | Authorization | Key Validation |
|---|---|---|
| `StoreDocumentRequest` | authenticated | Conditional file/URL per source_type, 200MB limit |
| `UpdateDocumentRequest` | DocumentPolicy::update | Partial update, extracted_text correction |
| `StoreCategoryRequest` | admin/teacher role | Unique slug, no circular parent |

### Policies (2)
| Class | Abilities | Admin Bypass |
|---|---|---|
| `DocumentPolicy` | viewAny, view, create, update, delete, reprocess, viewChunks | via Gate::before |
| `CategoryPolicy` | viewAny, view, create, update, delete | via Gate::before |

### API Resources (6)
`DocumentResource`, `CategoryResource`, `ProcessingJobResource`, `DocumentChunkResource`, `TranscriptResource`, `UserResource`

### Factories (3)
`DocumentFactory` (7 states), `CategoryFactory` (1 state), `ProcessingJobFactory` (4 states)

### Tests
| File | Type | Cases |
|---|---|---|
| `TextChunkerServiceTest` | Unit | 9 |
| `DocumentModelTest` | Unit | 12 |
| `ProcessingJobModelTest` | Unit | 4 |
| `DocumentTest` | Feature | 16 |
| `CategoryTest` | Feature | 9 |
| `ProcessingJobTest` | Feature | 4 |
| **Total** | | **53** |

---

## 3. Architecture Decisions

### 3.1 Single ChromaDB Collection with Metadata Filters
One collection `study_assistant_docs` stores all user documents. Per-user isolation is enforced via `where: {user_id: N}` filter on queries. This avoids collection-per-user management overhead while maintaining data isolation.

### 3.2 Named Redis Queues
Four queues — `default`, `ocr`, `transcribe`, `embed` — prevent heavy OCR/Whisper jobs from starving lightweight notification and embedding jobs. Worker priority order: `default,ocr,transcribe,embed`.

### 3.3 Bulk Insert with Manual UUID
`DocumentChunk::insert()` is used for bulk performance. Eloquent `boot()` model events do not fire on bulk inserts. `chroma_id` UUID is therefore generated explicitly per-row in the insert array. This is documented as a critical implementation note.

### 3.4 ProcessingJob State Machine
Each pipeline step creates a `ProcessingJob` record tracking `pending → processing → completed/failed`. This gives the frontend real-time pipeline visibility via `GET /api/documents/{id}/status`.

### 3.5 file_path Hidden from API
The internal MinIO path is added to `Document::$hidden`. Download access is granted only via presigned URLs from `GET /api/documents/{id}/download` (15-minute expiry).

---

## 4. Integration Points

### With U1 Infrastructure
| Integration | Status |
|---|---|
| MariaDB via Eloquent | ✅ Ready |
| Redis queue via Laravel Queue | ✅ Ready — named queues need docker-compose update |
| MinIO via `Storage::disk('s3')` | ✅ Ready |
| ChromaDB via HTTP client | ✅ Ready |
| Nginx proxy `/api/*` | ✅ Ready |

### With U2 AI Architecture
| Integration | Status |
|---|---|
| OpenAI `text-embedding-3-small` | ✅ EmbeddingService implemented |
| OpenAI Whisper `whisper-1` | ✅ TranscriptionService implemented |
| Anthropic Claude Sonnet | ✅ Config wired — used from Phase 3 |
| ChromaDB vector store | ✅ ChromaDbService implemented |
| Tesseract OCR + Thai pack | ✅ OcrService implemented |

---

## 5. Cross-Team Action Items

| Item | Team | Priority |
|---|---|---|
| Update `docker-compose.yml` queue-worker command to include `--queue=default,ocr,transcribe,embed` | U1 | HIGH |
| Merge `bootstrap/app.php` providers list (add EventServiceProvider) | Merge lead | HIGH |
| Confirm Phase 1 `UserFactory` exists with `role_id` and `is_active` fields | U1/Phase 1 | MEDIUM |
| Add `hasMany(Document::class)` relation to Phase 1 `User` model | Phase 1 | LOW |
