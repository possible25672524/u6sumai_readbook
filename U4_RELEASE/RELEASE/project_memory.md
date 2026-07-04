# Project Memory: AI Study Assistant Platform

> เอกสารนี้คือ "ความจำหลักของโครงการ" (Project Memory) สรุปจาก Spec ต้นฉบับ
> ใช้เป็นจุดอ้างอิงเดียว (Single Source of Truth) เมื่อพัฒนาต่อในแต่ละ Phase
> อัปเดตเอกสารนี้ทุกครั้งที่มีการตัดสินใจด้านสถาปัตยกรรมเปลี่ยนแปลง

**สถานะ:** ✅ Architecture Confirmed → พร้อมเข้า Phase 0
**อัปเดตล่าสุด:** 2026-06-22 (v2 — Tech Stack ตัดสินใจครบแล้ว)

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
   │                       [AI Service Layer]
   │                        ├─ Claude Sonnet (summarize / question-gen / RAG chat)
   │                        ├─ OpenAI text-embedding-3-small (embeddings)
   │                        └─ Whisper API (audio→text)
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

**คำเตือนเรื่อง Index:** ตารางที่ join บ่อย (`document_chunks`, `quiz_attempts`, `chat_messages`) ต้อง index FK + composite index ตาม query pattern จริง ไม่ใช่ index ทุกคอลัมน์

---

## 6. Security Checklist

- [ ] Laravel Sanctum (token-based)
- [ ] Role-Based Access Control (Admin/Teacher/Student) ระดับ Route + Policy
- [ ] CSRF Protection (สำหรับ session-based endpoints)
- [ ] XSS Protection (sanitize input, escape output)
- [ ] SQL Injection Protection (ใช้ Eloquent/Query Builder เท่านั้น ห้าม raw query ที่ไม่ bind parameter)
- [ ] File Validation (MIME type, ขนาดไฟล์, virus scan ถ้าเป็นไปได้)
- [ ] Rate Limiting (โดยเฉพาะ endpoint ที่เรียก AI API เพื่อคุมต้นทุน)

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
3. **AI Cost Control:** ระบบเรียก 3 AI Provider (Claude Sonnet, OpenAI Embedding, Whisper) ควรมี usage tracking/budget alert ตั้งแต่ Phase แรก ไม่ใช่เพิ่มทีหลัง
4. **RAG Grounding ต้องทดสอบเข้มข้น:** ข้อกำหนด "ตอบจากข้อมูลในระบบเท่านั้น" เป็นจุดที่ต้องมี evaluation set ทดสอบ hallucination โดยเฉพาะ
5. **Tesseract OCR ภาษาไทยมีความเสี่ยงเรื่องความแม่นยำ** โดยเฉพาะเอกสารสแกนคุณภาพต่ำ/ฟอนต์แปลก/ตารางซับซ้อน — ควรมี fallback flow ให้ผู้ใช้แก้ไขข้อความที่ OCR ผิดได้ และเก็บ confidence score ต่อ chunk
6. **แนะนำแบ่งเป็น Sprint/Phase ย่อย** แทนการ "ทำทุกอย่างพร้อมกัน" เพื่อให้ทดสอบและแก้ไขได้ทันก่อนหนี้ทางเทคนิคสะสม

---

## 10. แผนการพัฒนาที่เสนอ (Proposed Phasing)

| Phase | เนื้อหา |
|---|---|
| 0 | Project setup: init repo, Docker Compose (Laravel, MariaDB, Redis, ChromaDB, MinIO, Tesseract), env config |
| 1 | Auth + RBAC + DB Schema หลัก |
| 2 | Upload + Document Processing Pipeline (OCR/Transcribe/Embedding/ChromaDB) |
| 3 | AI Summarization + Flash Cards |
| 4 | Question Generator + Quiz Engine |
| 5 | RAG Chatbot + Quick Answer Mode |
| 6 | Study Planner + Exam Prediction |
| 7 | Analytics + Dashboard + Admin Panel |
| 8 | Security hardening, Performance tuning, Docker/Deployment |

---

## 11. Open Questions (เหลือที่ยังไม่ confirm)

- [ ] งบประมาณ/Rate limit สำหรับ AI API ต่อเดือนเป็นเท่าไร (Claude Sonnet + OpenAI Embedding + Whisper)
- [ ] ระดับความแม่นยำที่ยอมรับได้ของ Tesseract+Thai pack กับเอกสารจริง (ควรทดสอบกับ sample เอกสารจริงก่อนเข้า Phase 2)
- [ ] ขนาด/quota ของ MinIO ต่อผู้ใช้ (มี limit การอัปโหลดไหม)
- [ ] เวอร์ชัน Claude Sonnet ที่จะใช้ระบุชัดในการตั้งค่า env (เผื่อมีการอัปเดตเวอร์ชันโมเดลในอนาคต)

---

*หมายเหตุ: ไฟล์นี้คือเอกสาร "ความจำของโครงการ" ควรอัปเดตทุกครั้งที่มีการตัดสินใจสำคัญ เช่น เลือก Frontend แล้ว หรือเปลี่ยนแปลง schema*
