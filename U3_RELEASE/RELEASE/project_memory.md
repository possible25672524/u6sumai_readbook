# Project Memory: AI Study Assistant Platform

> เอกสารนี้คือ "ความจำหลักของโครงการ" (Project Memory) สรุปจาก Spec ต้นฉบับ
> ใช้เป็นจุดอ้างอิงเดียว (Single Source of Truth) เมื่อพัฒนาต่อในแต่ละ Phase
> อัปเดตเอกสารนี้ทุกครั้งที่มีการตัดสินใจด้านสถาปัตยกรรมเปลี่ยนแปลง

**สถานะ:** ✅ Phase 0 + Phase 1 + Phase 2 เสร็จสมบูรณ์ → พร้อมเข้า Phase 3 (รออนุมัติ)
**อัปเดตล่าสุด:** 2026-06-27 (v4 — Phase 2: Upload + Document Processing Pipeline เสร็จแล้ว)

---

## 1. ภาพรวมโครงการ (Project Overview)

ระบบช่วยเตรียมสอบด้วย AI — ผู้ใช้อัปโหลดสื่อการเรียน (เอกสาร/เสียง/วิดีโอ/รูปภาพ) ระบบจะ:
แยกข้อความ → สรุปเนื้อหา → สร้างข้อสอบ → ให้ตอบคำถามผ่าน RAG Chatbot → วางแผนการอ่าน → วิเคราะห์ผลการเรียน

**กลุ่มผู้ใช้:** Admin / Teacher / Student
**ขนาดที่รองรับ:** Concurrent users ≥ 100 คน

---

## 2. Technology Stack

| Layer | เทคโนโลยี | สถานะการตัดสินใจ |
|---|---|---|
| Frontend | React + Vite + PWA | ✅ Confirmed |
| Backend | Laravel 12 (REST API) | ✅ Confirmed |
| Database | MariaDB | ✅ Confirmed |
| Vector DB | ChromaDB | ✅ Confirmed (สำหรับ embedding/RAG) |
| Embedding Model | OpenAI `text-embedding-3-small` | ✅ Confirmed |
| OCR | Tesseract OCR + Thai Language Pack (`tha`) | ✅ Confirmed |
| AI Models | Claude Sonnet (summarize/QA-gen/chat), OpenAI Embedding, Whisper API (audio→text) | ✅ Confirmed |
| Storage | MinIO | ✅ Confirmed |
| Auth | Laravel Sanctum | ✅ Confirmed |
| Cache/Queue | Redis + Laravel Queue | ✅ Confirmed |
| Deployment | Docker Compose | ✅ Confirmed |

**Decision Log:**
| วันที่ | การตัดสินใจ |
|---|---|
| 2026-06-22 | Confirm: Frontend = React+Vite+PWA, Storage = MinIO, OCR = Tesseract (Thai pack), Embedding = OpenAI text-embedding-3-small, AI Chat/Gen = Claude Sonnet, Deployment = Docker Compose |
| 2026-06-23 | Phase 1 เสร็จสมบูรณ์: Sanctum แบบ API Token (Bearer) ไม่ใช้ SPA cookie-based, single role ต่อ user (`users.role_id`) + many-to-many role↔permission, ผู้สมัครเองได้ role student เท่านั้น, เพิ่ม `users.is_active` สำหรับ Phase 7 |
| 2026-06-27 | Phase 2 เสร็จสมบูรณ์: Document Upload + Processing Pipeline — ดูรายละเอียดด้านล่าง |

**Phase 2 Architecture Decisions:**
| การตัดสินใจ | เหตุผล |
|---|---|
| `DocumentChunk::insert()` bulk — ต้อง generate `chroma_id` UUID manually | Eloquent model events ไม่ทำงานกับ bulk insert |
| ChromaDB collection เดียว `study_assistant_docs` + metadata filter `user_id` | ง่ายต่อการ query แบบ cross-document และ per-user |
| Queue แยก: `default`, `ocr`, `transcribe`, `embed` | ป้องกัน OCR งานหนักบล็อก queue งาน embed ที่เบากว่า |
| `file_path` เพิ่มใน `$hidden` ของ Document model | ป้องกัน internal MinIO path รั่วออก API |
| FormRequest + Policy สองชั้น สำหรับ update | Defence in depth — Request authorize() เพิ่ม Policy check |
| `TextChunkerService` ไม่รู้เรื่อง page number | Phase 2 ใช้ text-level chunking เท่านั้น; per-page mapping เป็น Phase 4 concern |
| `claude-sonnet-4-5` เป็น model string ใน config | ปรับเปลี่ยนได้ผ่าน `ANTHROPIC_MODEL` env var |

---

## 3. สถาปัตยกรรมระดับสูง (High-Level Architecture)

```
[Client: React + Vite PWA]
        │ REST (Sanctum Token)
        ▼
[Laravel 12 API Gateway]
   ├─ AuthController / Sanctum
   ├─ DocumentController ──► ProcessDocumentJob (queue: default)
   │                              │
   │                    ┌─────────┴──────────┐
   │                    ▼                    ▼
   │             OcrDocumentJob       TranscribeAudioJob
   │             (queue: ocr)         (queue: transcribe)
   │                    │                    │
   │                    └─────────┬──────────┘
   │                              ▼
   │                   GenerateEmbeddingsJob
   │                      (queue: embed)
   │                              │
   │                    ┌─────────┴──────────┐
   │                    ▼                    ▼
   │             [ChromaDB]           [MariaDB: document_chunks]
   │             (vectors)            (text + metadata)
   │
   ├─ CategoryController
   ├─ ProcessingJobController
   ├─ QuizController / FlashcardController  [Phase 4]
   ├─ StudyPlannerController               [Phase 6]
   ├─ AnalyticsController                  [Phase 7]
   └─ AdminController                      [Phase 7]
        │
        ▼
[MariaDB] ◄──► [Redis: cache/queue] ◄──► [MinIO: ไฟล์ดิบ]
```

**หลักการออกแบบสำคัญ:**
- งานหนัก (Tesseract OCR, Whisper Transcribe, Embedding, Claude Generation) ต้องทำผ่าน **Queue Job แบบ Async** ทั้งหมด ห้ามทำ sync ใน HTTP request เพราะใช้เวลานาน
- AI Service ต้องเป็น Interface กลาง (เช่น `AIProviderInterface`) แม้ provider ถูก fix แล้ว (Claude Sonnet / OpenAI Embedding / Whisper) ก็ควรแยก concrete implementation ออกจาก business logic เพื่อรองรับการอัปเกรดเวอร์ชันโมเดลในอนาคต — ใช้ **Strategy Pattern**
- Chatbot ต้องตอบจาก "ข้อมูลในระบบเท่านั้น" → ต้อง enforce ด้วย system prompt + retrieval-grounding (ChromaDB) + ห้าม fallback ไปความรู้ทั่วไปของ Claude Sonnet
- Embedding ทุกจุด (Document chunks + Chat query) ต้องใช้โมเดลเดียวกัน (`text-embedding-3-small`) เสมอ มิฉะนั้น vector space จะไม่ comparable กัน
- **Bulk insert DocumentChunk ต้อง set `chroma_id` UUID ก่อน insert เสมอ** เพราะ `boot()` events ไม่ทำงาน

---

## 4. รายการ Feature Modules (14 โมดูล)

| # | โมดูล | Dependency หลัก | ความซับซ้อน | สถานะ |
|---|---|---|---|---|
| 1 | Authentication & RBAC | Sanctum, Role/Permission table | กลาง | ✅ Phase 1 |
| 2 | Dashboard | ข้อมูลรวมจากโมดูล 3,5,7,8,13 | ต่ำ (แต่ทำท้ายสุด) | ⏳ Phase 7 |
| 3 | Upload Learning Sources | Storage, Validation, Google Drive/YouTube API | กลาง-สูง | ✅ Phase 2 |
| 4 | Document Processing Pipeline | OCR engine, Whisper API, Queue, ChromaDB | **สูงสุด** (หัวใจระบบ) | ✅ Phase 2 |
| 5 | AI Summarization (7 รูปแบบ) | AI Service Layer | กลาง | ⏳ Phase 3 |
| 6 | Flash Cards | โมดูล 4 (เนื้อหา), Spaced repetition logic | กลาง | ⏳ Phase 3 |
| 7 | AI Question Generator (5 ประเภท) | AI Service Layer, อ้างอิงหน้าเอกสาร | สูง | ⏳ Phase 4 |
| 8 | Quiz Engine | โมดูล 7, Timer, Scoring | กลาง | ⏳ Phase 4 |
| 9 | AI Chatbot (RAG) | ChromaDB, AI Service, Citation tracking | **สูง** | ⏳ Phase 5 |
| 10 | Quick Answer Mode | เหมือนโมดูล 9 แต่ optimize latency | กลาง | ⏳ Phase 5 |
| 11 | Exam Prediction | สถิติจากโมดูล 7/8 + AI analysis | สูง (ขึ้นกับ data volume) | ⏳ Phase 6 |
| 12 | Study Planner | Algorithm จัดสรรเวลา/บท | กลาง | ⏳ Phase 6 |
| 13 | Analytics | Aggregation จากทุกโมดูล | กลาง | ⏳ Phase 7 |
| 14 | Admin Panel | CRUD + Log + Backup | กลาง | ⏳ Phase 7 |

---

## 5. Database Schema (Phase 2 เพิ่มเติม)

**Phase 2 Tables:**
- **`categories`** — หมวดหมู่เอกสาร (self-referential tree, created_by FK to users)
- **`documents`** — เอกสารหลัก (SoftDeletes, source_type enum, status, visibility)
- **`document_categories`** — pivot many-to-many
- **`processing_jobs`** — tracking pipeline steps per document
- **`transcripts`** — Whisper transcription results (1:1 with documents)
- **`document_chunks`** — text chunks mapped to ChromaDB (chroma_id UUID, is_embedded flag)
- **`notifications`** — Laravel database notification channel
- **`failed_jobs`** — Laravel queue failure tracking

**⚠️ Critical Implementation Note:**
`DocumentChunk::insert()` (bulk) bypasses Eloquent model `boot()` events.
**Always include `'chroma_id' => (string) Str::uuid()`** in the row array manually.
Affected files: `OcrDocumentJob.php`, `TranscribeAudioJob.php`

**Index Strategy:**
- `documents`: index on `user_id`, `status`, `source_type`, composite `(user_id, status)`, `created_at`
- `document_chunks`: index on `document_id`, composite `(document_id, chunk_index)`, `chroma_id`, composite `(document_id, is_embedded)`
- `processing_jobs`: index on `document_id`, composite `(document_id, job_type)`, `status`

---

## 6. API Endpoints (Phase 2)

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/categories` | ✅ | List categories (paginated or tree) |
| POST | `/api/categories` | Admin/Teacher | Create category |
| GET | `/api/categories/{id}` | ✅ | Get category detail |
| PUT | `/api/categories/{id}` | Owner/Admin | Update category |
| DELETE | `/api/categories/{id}` | Owner/Admin | Delete empty category |
| GET | `/api/documents` | ✅ | List documents (own + public/shared) |
| POST | `/api/documents` | ✅ | Upload document (triggers pipeline) |
| GET | `/api/documents/{id}` | Owner/Public | Get document |
| PUT | `/api/documents/{id}` | Owner | Update metadata/text |
| DELETE | `/api/documents/{id}` | Owner | Soft delete + cleanup |
| POST | `/api/documents/{id}/reprocess` | Owner | Re-trigger pipeline |
| GET | `/api/documents/{id}/status` | Owner | Pipeline status |
| GET | `/api/documents/{id}/chunks` | Owner | List text chunks |
| GET | `/api/documents/{id}/transcript` | Owner | Get Whisper transcript |
| GET | `/api/documents/{id}/download` | Owner | Get presigned MinIO URL |
| GET | `/api/documents/{id}/jobs` | Owner | Processing job history |
| GET | `/api/jobs/{job}` | Owner | Single job detail |
| GET | `/api/admin/jobs` | Admin | All system jobs (filtered) |

---

## 7. Queue Configuration

Named queues for Phase 2 (start worker with):
```bash
php artisan queue:work --queue=default,ocr,transcribe,embed --tries=3 --timeout=600
```

| Queue | Jobs | Timeout | Retries |
|---|---|---|---|
| `default` | ProcessDocumentJob, notifications | 60s | 1 |
| `ocr` | OcrDocumentJob | 600s | 3 |
| `transcribe` | TranscribeAudioJob | 600s | 3 |
| `embed` | GenerateEmbeddingsJob | 300s | 3 |

**⚠️ docker-compose.yml queue-worker command must be updated to:**
```yaml
command: ["php", "artisan", "queue:work", "--queue=default,ocr,transcribe,embed", "--tries=3", "--timeout=600"]
```

---

## 8. Security Checklist

- [x] Laravel Sanctum (token-based)
- [x] Role-Based Access Control (Admin/Teacher/Student) ระดับ Route + Policy
- [x] File Validation (MIME type, ขนาดไฟล์ max 200MB)
- [x] `file_path` hidden from API responses (MinIO internal path protection)
- [x] Presigned URLs for downloads (15-minute expiry)
- [x] CSRF Protection (สำหรับ session-based endpoints)
- [ ] XSS Protection (sanitize input, escape output) — Phase 8
- [ ] SQL Injection Protection (ใช้ Eloquent/Query Builder เท่านั้น) — enforced by convention
- [ ] Rate Limiting (โดยเฉพาะ endpoint ที่เรียก AI API เพื่อคุมต้นทุน) — Phase 8
- [ ] Virus scan for uploads — Phase 8

---

## 9. Performance Requirements

- รองรับ concurrent users ≥ 100
- Queue (Redis-backed) สำหรับงาน AI/OCR/Transcribe ทั้งหมด ✅
- Cache สำหรับ Dashboard/Analytics ที่ query ซ้ำบ่อย — Phase 7
- Lazy Loading ฝั่ง Frontend สำหรับรายการเอกสาร/ข้อสอบจำนวนมาก — Phase 7

---

## 10. แผนการพัฒนาที่เสนอ (Proposed Phasing)

| Phase | เนื้อหา | สถานะ |
|---|---|---|
| 0 | Project setup: init repo, Docker Compose, env config | ✅ Done |
| 1 | Auth + RBAC + DB Schema หลัก — Sanctum, roles/permissions, Auth API | ✅ Done |
| 2 | Upload + Document Processing Pipeline (OCR/Transcribe/Embedding/ChromaDB) | ✅ Done |
| 3 | AI Summarization + Flash Cards | ⏳ Next |
| 4 | Question Generator + Quiz Engine | ⏳ |
| 5 | RAG Chatbot + Quick Answer Mode | ⏳ |
| 6 | Study Planner + Exam Prediction | ⏳ |
| 7 | Analytics + Dashboard + Admin Panel | ⏳ |
| 8 | Security hardening, Performance tuning, Docker/Deployment | ⏳ |

---

## 11. Phase 2 Files Created

**Migrations (8):**
`create_categories_table`, `create_documents_table`, `create_document_categories_table`,
`create_processing_jobs_table`, `create_transcripts_table`, `create_document_chunks_table`,
`create_notifications_table`, `create_failed_jobs_table`

**Models (5):** `Category`, `Document`, `DocumentChunk`, `ProcessingJob`, `Transcript`

**Controllers (3):** `DocumentController`, `CategoryController`, `ProcessingJobController`

**Services (6):** `DocumentStorageService`, `OcrService`, `TranscriptionService`,
`EmbeddingService`, `ChromaDbService`, `TextChunkerService`

**Queue Jobs (4):** `ProcessDocumentJob`, `OcrDocumentJob`, `TranscribeAudioJob`, `GenerateEmbeddingsJob`

**Form Requests (3):** `StoreDocumentRequest`, `UpdateDocumentRequest`, `StoreCategoryRequest`

**Resources (6):** `DocumentResource`, `CategoryResource`, `ProcessingJobResource`,
`DocumentChunkResource`, `TranscriptResource`, `UserResource`

**Policies (2):** `DocumentPolicy`, `CategoryPolicy`

**Events (3):** `DocumentUploadedEvent`, `DocumentProcessedEvent`, `ProcessingFailedEvent`

**Listeners (2):** `SendDocumentProcessedNotification`, `HandleProcessingFailed`

**Notifications (1):** `DocumentProcessedNotification`

**Providers (2):** `AppServiceProvider` (updated), `EventServiceProvider` (new)

**Config (3):** `config/services.php`, `config/filesystems.php`, `config/queue.php`

**Factories (3):** `DocumentFactory`, `CategoryFactory`, `ProcessingJobFactory`

**Tests — Unit (3):** `TextChunkerServiceTest`, `DocumentModelTest`, `ProcessingJobModelTest`

**Tests — Feature (3):** `DocumentTest`, `CategoryTest`, `ProcessingJobTest`

**Other:** `routes/api.php` (Phase 1 + Phase 2), `bootstrap/app.php`, `tests/CreatesUsers.php`

---

## 12. Open Questions (Phase 3+ scope)

- [ ] งบประมาณ/Rate limit สำหรับ AI API ต่อเดือนเป็นเท่าไร (Claude Sonnet + OpenAI Embedding + Whisper)
- [ ] ระดับความแม่นยำที่ยอมรับได้ของ Tesseract+Thai pack กับเอกสารจริง (ควรทดสอบกับ sample)
- [ ] ขนาด/quota ของ MinIO ต่อผู้ใช้ (มี limit การอัปโหลดไหม)
- [ ] เวอร์ชัน Claude Sonnet ที่จะใช้ระบุชัด (ปัจจุบัน: `claude-sonnet-4-5` ผ่าน env `ANTHROPIC_MODEL`)
- [ ] Phase 3 ต้องการ `AIProviderInterface` (Strategy Pattern) หรือ concrete services เพียงพอ
- [ ] Google Drive / YouTube URL processing (Phase 2 รับ URL แต่ยัง process เป็นแค่ placeholder)
- [ ] `docker-compose.yml` queue-worker: ต้องเพิ่ม named queues `ocr,transcribe,embed`

---

*หมายเหตุ: ไฟล์นี้คือเอกสาร "ความจำของโครงการ" ควรอัปเดตทุกครั้งที่มีการตัดสินใจสำคัญ*
