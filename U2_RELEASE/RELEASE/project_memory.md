# Project Memory: AI Study Assistant Platform

> เอกสารนี้คือ "ความจำหลักของโครงการ" (Project Memory) สรุปจาก Spec ต้นฉบับ
> ใช้เป็นจุดอ้างอิงเดียว (Single Source of Truth) เมื่อพัฒนาต่อในแต่ละ Phase
> อัปเดตเอกสารนี้ทุกครั้งที่มีการตัดสินใจด้านสถาปัตยกรรมเปลี่ยนแปลง

**สถานะ:** ✅ Phase 0 + Phase 1 เสร็จสมบูรณ์ | ✅ Phase 2 (AI Provider Layer) เสร็จสมบูรณ์ → พร้อมเข้า Phase 3
**อัปเดตล่าสุด:** 2026-06-24 (v4 — AI Provider Architecture: U2 deliverable สมบูรณ์)

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
| 2026-06-24 | Phase 2 AI Provider Layer เสร็จสมบูรณ์: Strategy Pattern + Interface-based DI, ClaudeProvider (primary), OpenAIChatProvider (fallback), OpenAIEmbeddingProvider (text-embedding-3-small), WhisperProvider, AIManager registry, 5 high-level services, AIUsageLog tracking, 26 PHP files, 5 test files, remediated 7 bugs found during audit |

---

## 3. สถาปัตยกรรมระดับสูง (High-Level Architecture)

```
[Client: React + Vite PWA]
        │ REST (Sanctum Token)
        ▼
[Laravel 12 API Gateway]
   ├─ AuthController / Sanctum
   ├─ DocumentController ──► Queue Job: Extract / Tesseract OCR (tha) / Whisper Transcribe
   │                              │
   │                              ▼
   │                       [AI Service Layer]  ← Phase 2 COMPLETE
   │                        ├─ AIManager (Strategy Pattern dispatcher)
   │                        │   ├─ ClaudeProvider      (chat/summarize/questions/RAG)
   │                        │   ├─ OpenAIChatProvider   (fallback chat)
   │                        │   ├─ OpenAIEmbeddingProvider (text-embedding-3-small)
   │                        │   └─ WhisperProvider      (audio→text)
   │                        ├─ SummarizationService     (7 formats)
   │                        ├─ QuestionGenerationService (5 types)
   │                        ├─ RAGChatService           (grounded chatbot)
   │                        ├─ EmbeddingService         (chunking + ChromaDB prep)
   │                        └─ TranscriptionService     (audio pipeline)
   │                              │
   │                              ▼
   │                       [ChromaDB] (embeddings/RAG retrieval)
   ├─ QuizController / FlashcardController
   ├─ StudyPlannerController
   ├─ AnalyticsController
   └─ AdminController
        │
        ▼
[MariaDB] ◄──► [Redis: cache/queue] ◄──► [MinIO: ไฟล์ดิบ]
```

**หลักการออกแบบสำคัญ:**
- งานหนัก (Tesseract OCR, Whisper Transcribe, Embedding, Claude Generation) ต้องทำผ่าน **Queue Job แบบ Async** ทั้งหมด ห้ามทำ sync ใน HTTP request เพราะใช้เวลานาน
- AI Service ต้องเป็น Interface กลาง (เช่น `AIProviderInterface`) แม้ provider ถูก fix แล้ว (Claude Sonnet / OpenAI Embedding / Whisper) ก็ควรแยก concrete implementation ออกจาก business logic เพื่อรองรับการอัปเกรดเวอร์ชันโมเดลในอนาคต — ใช้ **Strategy Pattern**
- Chatbot ต้องตอบจาก "ข้อมูลในระบบเท่านั้น" → ต้อง enforce ด้วย system prompt + retrieval-grounding (ChromaDB) + ห้าม fallback ไปความรู้ทั่วไปของ Claude Sonnet
- Embedding ทุกจุด (Document chunks + Chat query) ต้องใช้โมเดลเดียวกัน (`text-embedding-3-small`) เสมอ มิฉะนั้น vector space จะไม่ comparable กัน

---

## 4. รายการ Feature Modules (14 โมดูล)

| # | โมดูล | Dependency หลัก | ความซับซ้อน |
|---|---|---|---|
| 1 | Authentication & RBAC | Sanctum, Role/Permission table | กลาง |
| 2 | Dashboard | ข้อมูลรวมจากโมดูล 3,5,7,8,13 | ต่ำ (แต่ทำท้ายสุด) |
| 3 | Upload Learning Sources | Storage, Validation, Google Drive/YouTube API | กลาง-สูง |
| 4 | Document Processing Pipeline | OCR engine, Whisper API, Queue, ChromaDB | **สูงสุด** (หัวใจระบบ) |
| 5 | AI Summarization (7 รูปแบบ) | AI Service Layer | กลาง |
| 6 | Flash Cards | โมดูล 4 (เนื้อหา), Spaced repetition logic | กลาง |
| 7 | AI Question Generator (5 ประเภท) | AI Service Layer, อ้างอิงหน้าเอกสาร | สูง |
| 8 | Quiz Engine | โมดูล 7, Timer, Scoring | กลาง |
| 9 | AI Chatbot (RAG) | ChromaDB, AI Service, Citation tracking | **สูง** |
| 10 | Quick Answer Mode | เหมือนโมดูล 9 แต่ optimize latency | กลาง |
| 11 | Exam Prediction | สถิติจากโมดูล 7/8 + AI analysis | สูง (ขึ้นกับ data volume) |
| 12 | Study Planner | Algorithm จัดสรรเวลา/บท | กลาง |
| 13 | Analytics | Aggregation จากทุกโมดูล | กลาง |
| 14 | Admin Panel | CRUD + Log + Backup | กลาง |

**ข้อสังเกต:** โมดูล 4 (Document Processing) เป็น **dependency ของทุกโมดูลที่เหลือ** ควรเป็น Phase แรกที่ทำให้เสร็จสมบูรณ์และทดสอบหนักที่สุด

---

## 5. Database (สิ่งที่ต้องออกแบบ)

ต้องมี ER Diagram + Migration + FK + Index ครบ โดยกลุ่มตารางหลักที่คาดว่าต้องมี:

- **Users & Auth:** `users`, `roles`, `permissions`, `role_permissions`, `personal_access_tokens` (Sanctum)
- **Content:** `documents`, `document_chunks` (mapping ไปยัง ChromaDB vector id), `categories`, `document_categories`
- **Processing:** `processing_jobs` (สถานะ OCR/Transcribe/Embedding), `transcripts`
- **Summaries:** `summaries` (type: short/detailed/bullet/exam/mindmap/table/keypoints)
- **Flashcards:** `flashcard_sets`, `flashcards`, `flashcard_reviews` (spaced repetition score)
- **Quiz:** `question_banks`, `questions`, `question_choices`, `quizzes`, `quiz_attempts`, `quiz_answers`
- **Chat:** `chat_sessions`, `chat_messages`, `chat_citations`
- **Planner:** `study_plans`, `study_plan_items`
- **Analytics:** `study_sessions` (เวลาที่ใช้), `analytics_snapshots`
- **Admin:** `activity_logs`, `system_backups`
- **AI Tracking:** ✅ `ai_usage_logs` — migration created (Phase 2)

**คำเตือนเรื่อง Index:** ตารางที่ join บ่อย (`document_chunks`, `quiz_attempts`, `chat_messages`) ต้อง index FK + composite index ตาม query pattern จริง ไม่ใช่ index ทุกคอลัมน์

---

## 6. Security Checklist

- [x] Laravel Sanctum (token-based) ← Phase 1
- [x] Role-Based Access Control (Admin/Teacher/Student) ระดับ Route + Policy ← Phase 1
- [ ] CSRF Protection (สำหรับ session-based endpoints)
- [ ] XSS Protection (sanitize input, escape output)
- [ ] SQL Injection Protection (ใช้ Eloquent/Query Builder เท่านั้น ห้าม raw query ที่ไม่ bind parameter)
- [ ] File Validation (MIME type, ขนาดไฟล์, virus scan ถ้าเป็นไปได้)
- [x] Rate Limiting (AI endpoints — ผ่าน HasRetry + AIRateLimitException) ← Phase 2

---

## 7. Performance Requirements

- รองรับ concurrent users ≥ 100
- Queue (Redis-backed) สำหรับงาน AI/OCR/Transcribe ทั้งหมด
- Cache สำหรับ Dashboard/Analytics ที่ query ซ้ำบ่อย
- Lazy Loading ฝั่ง Frontend สำหรับรายการเอกสาร/ข้อสอบจำนวนมาก

---

## 8. Deliverables ที่ต้องส่งมอบ

1. System Architecture Diagram
2. Use Case Diagram
3. ER Diagram
4. Database Schema + Migration files
5. API Specification (Request/Response/Validation)
6. Frontend Folder Structure + Routing + State Management
7. Source Code (Backend + Frontend)
8. Installation Guide
9. Docker Configuration (docker-compose: Laravel, MariaDB, Redis, ChromaDB, MinIO, Tesseract/OCR worker)
10. Deployment Guide

---

## 9. ข้อสังเกตเชิงสถาปัตยกรรม (Critical Notes)

ระบบนี้มี**ขนาดใหญ่ระดับ Enterprise** การพัฒนาทั้งหมดในครั้งเดียวมีความเสี่ยงสูง ข้อเสนอแนะ:

1. **Tech Stack confirm ครบแล้ว** (ดู Decision Log ในหัวข้อ 2) — ไม่มีตัวเลือกค้างที่ต้องตัดสินใจก่อนเริ่ม Phase 0 อีก
2. **เริ่มจาก Phase ที่เป็น Foundation:** Auth → Document Processing Pipeline → ChromaDB Integration ก่อน เพราะโมดูลอื่นเกือบทั้งหมดพึ่งพาสิ่งนี้
3. **AI Cost Control:** ✅ ระบบมี `AIUsageLog` + `TracksUsage` trait + Redis daily counter ตั้งแต่ Phase 2 แล้ว
4. **RAG Grounding ต้องทดสอบเข้มข้น:** ข้อกำหนด "ตอบจากข้อมูลในระบบเท่านั้น" เป็นจุดที่ต้องมี evaluation set ทดสอบ hallucination โดยเฉพาะ
5. **Tesseract OCR ภาษาไทยมีความเสี่ยงเรื่องความแม่นยำ** โดยเฉพาะเอกสารสแกนคุณภาพต่ำ/ฟอนต์แปลก/ตารางซับซ้อน — ควรมี fallback flow ให้ผู้ใช้แก้ไขข้อความที่ OCR ผิดได้ และเก็บ confidence score ต่อ chunk
6. **แนะนำแบ่งเป็น Sprint/Phase ย่อย** แทนการ "ทำทุกอย่างพร้อมกัน" เพื่อให้ทดสอบและแก้ไขได้ทันก่อนหนี้ทางเทคนิคสะสม

---

## 10. แผนการพัฒนาที่เสนอ (Proposed Phasing)

| Phase | เนื้อหา |
|---|---|
| 0 | ✅ Project setup: init repo, Docker Compose (Laravel, MariaDB, Redis, ChromaDB, MinIO, Tesseract), env config |
| 1 | ✅ Auth + RBAC + DB Schema หลัก — Sanctum (Bearer token), roles/permissions/role_permissions, 6 Auth endpoints, Seeders, API doc (`backend/docs/API_AUTH.md`) |
| 2 | ✅ AI Provider Architecture — AIManager, 4 Providers, 5 Services, DTOs, Exceptions, Traits, Config, Migration, Tests, Docs |
| 3 | AI Summarization + Flash Cards |
| 4 | Question Generator + Quiz Engine |
| 5 | RAG Chatbot + Quick Answer Mode |
| 6 | Study Planner + Exam Prediction |
| 7 | Analytics + Dashboard + Admin Panel |
| 8 | Security hardening, Performance tuning, Docker/Deployment |

---

## 11. Open Questions (เหลือที่ยังไม่ confirm)

- [ ] งบประมาณ/Rate limit สำหรับ AI API ต่อเดือนเป็นเท่าไร (Claude Sonnet + OpenAI Embedding + Whisper)
- [ ] ระดับความแม่นยำที่ยอมรับได้ของ Tesseract+Thai pack กับเอกสารจริง (ควรทดสอบกับ sample เอกสารจริงก่อนเข้า Phase 3)
- [ ] ขนาด/quota ของ MinIO ต่อผู้ใช้ (มี limit การอัปโหลดไหม)
- [x] เวอร์ชัน Claude Sonnet — ใช้ `claude-sonnet-4-5` กำหนดผ่าน `ANTHROPIC_MODEL` ใน `.env`
- [ ] ChromaDB client library สำหรับ PHP — ยังไม่ได้เลือก (Phase 3 ต้องตัดสินใจ: chromadb/chromadb หรือ HTTP client โดยตรง)

---

## 12. AI Provider Architecture (Phase 2) — NEW

### สถานะ: ✅ สมบูรณ์ พร้อม merge

### 12.1 ไฟล์ที่สร้างทั้งหมด (26 ไฟล์)

**Contracts (Interfaces):**
```
app/Contracts/AI/AIProviderInterface.php
app/Contracts/AI/EmbeddingProviderInterface.php
app/Contracts/AI/TranscriptionProviderInterface.php
```

**Provider Implementations:**
```
app/Services/AI/Providers/ClaudeProvider.php
app/Services/AI/Providers/OpenAIChatProvider.php
app/Services/AI/Providers/OpenAIEmbeddingProvider.php
app/Services/AI/Providers/WhisperProvider.php
```

**AIManager (Strategy Pattern hub):**
```
app/Services/AI/AIManager.php
```

**DTOs (Value Objects):**
```
app/Services/AI/DTOs/ChatMessage.php
app/Services/AI/DTOs/ChatResponse.php
app/Services/AI/DTOs/AIUsage.php
app/Services/AI/DTOs/EmbeddingResponse.php
app/Services/AI/DTOs/TranscriptionResponse.php
```

**Exceptions:**
```
app/Services/AI/Exceptions/AIProviderException.php
app/Services/AI/Exceptions/AIRateLimitException.php
```

**Concerns (Traits):**
```
app/Services/AI/Concerns/HasRetry.php       (exponential backoff, jitter)
app/Services/AI/Concerns/TracksUsage.php    (DB + Redis usage logging)
```

**High-Level Services:**
```
app/Services/SummarizationService.php       (Module 5 — 7 formats)
app/Services/QuestionGenerationService.php  (Module 7 — 5 types, JSON output)
app/Services/RAGChatService.php             (Module 9/10 — grounded chatbot)
app/Services/EmbeddingService.php           (Module 4 — chunking + batch embed)
app/Services/TranscriptionService.php       (Module 4 — Whisper pipeline)
```

**Service Provider:**
```
app/Providers/AIServiceProvider.php
```

**Model + Migration:**
```
app/Models/AIUsageLog.php
database/migrations/2026_06_23_230621_create_ai_usage_logs_table.php
```

**Config:**
```
config/ai.php
```

**Tests (5 files):**
```
tests/Unit/AI/ClaudeProviderTest.php            (12 tests)
tests/Unit/AI/OpenAIEmbeddingProviderTest.php   (13 tests)
tests/Unit/AI/WhisperProviderTest.php           (14 tests)
tests/Unit/AI/AIManagerTest.php                 (16 tests)
tests/Unit/AI/SummarizationServiceTest.php      (17 tests)
```

**Documentation:**
```
docs/AI_INTEGRATION_GUIDE.md
```

### 12.2 Architecture Pattern

```
AIManager (Strategy Pattern)
    │
    ├── registerChatProvider('claude', ClaudeProvider)
    ├── registerChatProvider('openai', OpenAIChatProvider)
    ├── EmbeddingProviderInterface → OpenAIEmbeddingProvider
    └── TranscriptionProviderInterface → WhisperProvider

All providers implement:
    ├── HasRetry trait (exponential backoff, 3 retries, jitter)
    └── TracksUsage trait (AIUsageLog DB + Redis daily counter)
```

### 12.3 Provider Summary

| Provider | Interface | Model | Primary Use |
|---|---|---|---|
| `ClaudeProvider` | `AIProviderInterface` | `claude-sonnet-4-5` | Summarize, Q-gen, RAG chat |
| `OpenAIChatProvider` | `AIProviderInterface` | `gpt-4o-mini` | Fallback text generation |
| `OpenAIEmbeddingProvider` | `EmbeddingProviderInterface` | `text-embedding-3-small` | Document + query embeddings |
| `WhisperProvider` | `TranscriptionProviderInterface` | `whisper-1` | Audio/video → text |

### 12.4 Known Limitations

| # | ข้อจำกัด | ผลกระทบ | แนวทางแก้ไข |
|---|---|---|---|
| 1 | `AIManager::healthCheck()` ไม่ ping `WhisperProvider` (TranscriptionProviderInterface ไม่มี `ping()`) | ตรวจสุขภาพ Whisper ไม่ได้ | เพิ่ม `ping()` ใน TranscriptionProviderInterface ใน Phase 3 |
| 2 | `TranscriptionResponse::wordCount()` ใช้ `str_word_count()` ซึ่งไม่นับคำภาษาไทย | word count ผิดสำหรับ Thai | ใช้ `preg_match_all` + Unicode word boundary ใน Phase 3 |
| 3 | Budget alert threshold ใน `config/ai.php` ถูกกำหนดแต่ยังไม่มีโค้ดบังคับใช้ | ไม่มีการแจ้งเตือนเมื่อใช้เกิน budget | สร้าง `BudgetGuard` middleware ใน Phase 3 |
| 4 | `ClaudeProvider::ping()` เรียก API จริง (ไม่ใช่ endpoint health check) | อาจเสียเงิน token ถ้าเรียกบ่อย | จำกัดการเรียก `healthCheck()` ใน cron job เท่านั้น |
| 5 | `OpenAIEmbeddingProvider` ไม่ตรวจ `dimensions` option จาก caller | ถ้า caller ส่ง `dimensions: 256` vector จะไม่ตรง 1536 | เพิ่ม validation และ override `getDimensions()` return ใน Phase 3 |

### 12.5 Integration Points สำหรับ Phase ถัดไป

| Phase | ต้องใช้จาก AI Layer | หมายเหตุ |
|---|---|---|
| Phase 3 (Summarization + Flashcards) | `SummarizationService::summarize()` | พร้อมใช้งาน |
| Phase 4 (Questions + Quiz) | `QuestionGenerationService::generate()` | พร้อมใช้งาน, parse JSON output |
| Phase 4 (Pipeline) | `EmbeddingService::embedDocument()`, `TranscriptionService::transcribe()` | ต้องมี ChromaDB client ก่อน |
| Phase 5 (RAG Chat) | `RAGChatService::answer()`, `EmbeddingService::embedQuery()` | ต้องมี ChromaDB client ก่อน |
| Phase 7 (Analytics) | `AIUsageLog` model + scopes | พร้อมใช้งาน |

### 12.6 Bootstrap Registration

```php
// bootstrap/app.php — เพิ่ม 1 บรรทัด:
->withProviders([App\Providers\AIServiceProvider::class])

// จากนั้นรัน:
// php artisan vendor:publish --tag=ai-config
// php artisan migrate
```

### 12.7 Testing Status

| Test File | Tests | Coverage Focus |
|---|---|---|
| `ClaudeProviderTest` | 12 | HTTP fake, auth headers, system prompt extraction, 429/5xx handling, token usage |
| `OpenAIEmbeddingProviderTest` | 13 | Batch embed, empty batch, size limit, cosine similarity, dimension mismatch |
| `WhisperProviderTest` | 14 | Multipart format (bug fix validation), file validation, all supported extensions |
| `AIManagerTest` | 16 | Provider routing, delegation, health check, registration, override |
| `SummarizationServiceTest` | 17 | All 7 formats, language instructions, system prompt structure, options pass-through |
| **Total** | **72** | **Core AI layer** |

### 12.8 Bugs Remediated During Phase 2

| # | Bug | Severity | สถานะ |
|---|---|---|---|
| 1 | `RAGChatService` missing — AIServiceProvider boot fatal | FATAL | ✅ Fixed |
| 2 | `TranscriptionService` missing — AIServiceProvider boot fatal | FATAL | ✅ Fixed |
| 3 | `AIUsageLog` model missing — class not found on first AI call | FATAL | ✅ Fixed |
| 4 | `ai_usage_logs` migration missing — QueryException on first AI call | FATAL | ✅ Fixed |
| 5 | `HasRetry` `context:` named param — PHP Fatal when retries exhausted | HIGH | ✅ Fixed |
| 6 | `WhisperProvider` multipart — form fields sent as file attachments | HIGH | ✅ Fixed |
| 6b | `WhisperProvider` fopen without fclose — file descriptor leak | MEDIUM | ✅ Fixed |
| 7 | `TracksUsage` cache race condition — non-atomic read-then-write | MEDIUM | ✅ Fixed |

---

## 13. Open Questions (เพิ่มเติม Phase 2)

- [ ] ChromaDB PHP client library: ใช้ HTTP client โดยตรงหรือมี wrapper library? ต้องตัดสินใจก่อน Phase 3
- [ ] ต้องการ `ping()` ใน `TranscriptionProviderInterface` ไหม? (ปัจจุบัน healthCheck ไม่ครอบคลุม Whisper)
- [ ] Budget alert: ส่ง Slack notification หรือ email? ใช้ Laravel Notification หรือ external service?

---

*หมายเหตุ: ไฟล์นี้คือเอกสาร "ความจำของโครงการ" ควรอัปเดตทุกครั้งที่มีการตัดสินใจสำคัญ เช่น เลือก Frontend แล้ว หรือเปลี่ยนแปลง schema*
