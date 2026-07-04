# Project Memory: AI Study Assistant Platform

> เอกสารนี้คือ "ความจำหลักของโครงการ" (Project Memory) สรุปจาก Spec ต้นฉบับ
> ใช้เป็นจุดอ้างอิงเดียว (Single Source of Truth) เมื่อพัฒนาต่อในแต่ละ Phase
> อัปเดตเอกสารนี้ทุกครั้งที่มีการตัดสินใจด้านสถาปัตยกรรมเปลี่ยนแปลง

**สถานะ:** ✅ Phase 0 + Phase 1 เสร็จสมบูรณ์ → ✅ Phase 2a (Infrastructure) เสร็จสมบูรณ์ + Accepted + Packaged for release (v4.2)
→ ⏳ Phase 2b (Application code: OCR/Whisper/Embedding services, upload
controllers, document-processing jobs) **ยังไม่เริ่ม**
**อัปเดตล่าสุด:** 2026-06-28 (v4.2 — Team U1/DevOps: Release packaging, U1_RELEASE.zip)

> **หมายเหตุสำคัญเรื่อง scope:** งานที่เสร็จในรอบนี้คือ "โครงสร้างพื้นฐาน Docker"
> สำหรับ Document Processing Pipeline (compose files, Dockerfiles, health
> checks, env, deployment docs) เท่านั้น **ไม่รวม** โค้ด Laravel จริงสำหรับ
> OCR Service / Whisper Service / Embedding Service / Upload Controller /
> Queue Jobs — งานเหล่านั้นยังเป็น Phase 2b ที่รอทีม Backend เริ่มทำ

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
| Vector DB | ChromaDB | ✅ Confirmed (pinned `1.5.7`, see §9.4) |
| Embedding Model | OpenAI `text-embedding-3-small` | ✅ Confirmed |
| OCR | Tesseract OCR + Thai Language Pack (`tha`) | ✅ Confirmed — **now built into `backend/Dockerfile`** |
| AI Models | Claude Sonnet (`claude-sonnet-4-6`) for summarize/QA-gen/chat, OpenAI Embedding, Whisper API (audio→text) | ✅ Confirmed |
| Storage | MinIO | ⚠️ **Confirmed but flagged as a critical infra risk — see §9.4/§11.** Re-evaluation needed before production launch. |
| Auth | Laravel Sanctum | ✅ Confirmed (Phase 1) |
| Cache/Queue | Redis + Laravel Queue | ✅ Confirmed — **now split into 3 named-queue worker containers, see §9.4** |
| Deployment | Docker Compose | ✅ Confirmed — **now production-safe base + dev override + prod overlay, see §9.4** |

**Decision Log:**
| วันที่ | การตัดสินใจ |
|---|---|
| 2026-06-22 | Confirm: Frontend = React+Vite+PWA, Storage = MinIO, OCR = Tesseract (Thai pack), Embedding = OpenAI text-embedding-3-small, AI Chat/Gen = Claude Sonnet, Deployment = Docker Compose |
| 2026-06-23 (v3) | Phase 1 เสร็จสมบูรณ์: Sanctum แบบ API Token (Bearer) ไม่ใช้ SPA cookie-based, single role ต่อ user (`users.role_id`) + many-to-many role↔permission, ผู้สมัครเองได้ role student เท่านั้น, เพิ่ม `users.is_active` สำหรับ Phase 7 |
| 2026-06-23 (v4) | **Phase 2 Infrastructure เสร็จสมบูรณ์ (Team U1/DevOps):** ดูรายละเอียดเต็มที่ §9.4 — สรุป: (1) `docker-compose.yml` แยกเป็น base (prod-safe) + `.override.yml` (dev) + `.prod.yml` (overlay) แทนไฟล์เดียวเดิม, (2) เพิ่ม Tesseract+ffmpeg+poppler เข้า `backend/Dockerfile`, (3) แยก queue worker เป็น 3 ตัว (ocr/ai/default), (4) เพิ่ม healthcheck ให้ทุก service, (5) สถาปัตยกรรม ingress ใหม่: `frontend` (nginx, prod build) เป็น public entrypoint เดียว proxy `/api` ไปยัง `nginx`(API gateway, internal-only) → `backend`, (6) ตรวจพบความเสี่ยงร้ายแรงเรื่อง MinIO Docker image (ดู §11) |
| 2026-06-23 (v4.1) | **Acceptance-review defect closure (Team U1/DevOps):** สร้าง `PHASE_2_INFRASTRUCTURE.md` ที่ขาดไปใน v4; แก้ 2 defect ใน `docker-compose.yml`/`.override.yml` (dev-credential fallback ที่ใช้งานจริงไม่ได้ → ลบทิ้งและเพิ่ม `scripts/bootstrap-env.sh`; `deploy.resources.limits` ไม่รับประกันผลบน Compose รุ่นเก่า → เพิ่ม `mem_limit`/`cpus` เป็น fallback); แก้ `backend/Dockerfile` ที่อ้างว่า immutable ทั้งที่ยังไม่มี `composer.json` ให้ build-time install ได้จริง → ทำเป็น conditional ที่ self-upgrade เมื่อ skeleton ถูก commit; สร้าง `backend/.env.example`/`frontend/.env.example` แบบสมบูรณ์ (reconstructed) พร้อม `ENV_VARIABLES.md` เป็น spec+merge instructions เต็มรูปแบบ. MinIO risk ยังคง escalate ไปทีม/PM ตามเดิม ไม่ถือเป็น blocker ของงาน infrastructure |
| 2026-06-28 (v4.2) | **Release packaging (Team U1/DevOps):** หลังผ่าน independent acceptance review (ACCEPT U1) แล้ว จัดบรรจุ deliverable ทั้งหมดเป็น `RELEASE/` (`infrastructure/`, `docker/`, `deployment/`, `scripts/`, `docs/`) พร้อมเอกสารใหม่ 6 ฉบับ (`README.md`, `CHANGELOG.md`, `RELEASE_MANIFEST.md`, `docs/IMPLEMENTATION_REPORT.md`, `docs/VALIDATION_REPORT.md`, `docs/ACCEPTANCE_REPORT.md`) แพ็กเป็น `U1_RELEASE.zip`. **ไม่มีการเปลี่ยนแปลงฟังก์ชันใดๆ ในรอบนี้** — เป็นการจัดเรียง/แพ็กของสิ่งที่ accept แล้วเท่านั้น ดู `RELEASE/RELEASE_MANIFEST.md` สำหรับ inventory เต็มรูปแบบ (Files Created/Modified, Infrastructure Components, Env Vars, Docker Services, Health Checks, Known Limitations, Runtime Requirements) |

---

## 3. สถาปัตยกรรมระดับสูง (High-Level Architecture)

> อัปเดต v4: เพิ่มชั้น ingress (`frontend` เป็น public entrypoint เดียว, `nginx`
> เป็น internal API gateway) และแยก queue worker ตามลักษณะงาน

```
[Client: Browser]
        │ HTTPS (terminate TLS at your LB/reverse proxy - not implemented here)
        ▼
[frontend container — nginx serving React SPA build]  <── ONLY public port
   ├─ serves static SPA
   └─ proxy /api/* ──────────────┐
                                 ▼
                    [nginx container — API gateway, internal-only]
                                 │ fastcgi
                                 ▼
                    [backend container — Laravel 12 / PHP-FPM]
                    Tesseract OCR + ffmpeg + poppler-utils baked in
                    ├─ DocumentController ──► Queue Job
                    │                              │
              ┌──────────────┬──────────────┬──────┴───────┐
              ▼              ▼              ▼              ▼
   queue-worker-ocr   queue-worker-ai   queue-worker-default
   (Tesseract OCR,    (Whisper API,                catch-all
    ffmpeg extract)    OpenAI Embedding,
                        Claude Sonnet gen)
              │              │
              ▼              ▼
        [ChromaDB]     [Claude Sonnet / OpenAI APIs]
        (embeddings/RAG retrieval, internal-only)
   ├─ QuizController / FlashcardController
   ├─ StudyPlannerController
   ├─ AnalyticsController
   └─ AdminController
        │
        ▼
[MariaDB] ◄──► [Redis: cache/queue] ◄──► [MinIO: ไฟล์ดิบ — ⚠️ see §11]
   (all internal-only, no host ports published in production)
```

**หลักการออกแบบสำคัญ (เดิม, ยังใช้อยู่):**
- งานหนัก (Tesseract OCR, Whisper Transcribe, Embedding, Claude Generation) ต้องทำผ่าน **Queue Job แบบ Async** ทั้งหมด ห้ามทำ sync ใน HTTP request เพราะใช้เวลานาน — **v4: implement เป็น 2 named queue groups (`ocr,transcribe` และ `embedding,ai-generation`) รันคนละ container เพื่อ scale อิสระกัน**
- AI Service ต้องเป็น Interface กลาง (เช่น `AIProviderInterface`) แม้ provider ถูก fix แล้ว ก็ควรแยก concrete implementation ออกจาก business logic — **ยังเป็น Phase 2b (โค้ด), infra เตรียม env vars ไว้แล้ว (`ANTHROPIC_MODEL`, `OPENAI_EMBEDDING_MODEL`, `OPENAI_WHISPER_MODEL`)**
- Chatbot ต้องตอบจาก "ข้อมูลในระบบเท่านั้น" → enforce ด้วย system prompt + retrieval-grounding (ChromaDB) — infra เตรียม `proxy_buffering off` ไว้ใน nginx config ทั้งสองชั้นรอ streaming response แล้ว
- Embedding ทุกจุดต้องใช้โมเดลเดียวกัน (`text-embedding-3-small`) เสมอ

---

## 4. รายการ Feature Modules (14 โมดูล)

(ไม่เปลี่ยนแปลงจาก v3 — ดูตารางเต็มในเอกสารเดิม)

**ข้อสังเกต:** โมดูล 4 (Document Processing) เป็น **dependency ของทุกโมดูลที่เหลือ**
— v4 เพิ่ม infra ที่จำเป็น (OCR/ffmpeg toolchain, named queues, ChromaDB)
ให้พร้อมรับโมดูลนี้แล้ว ทีม Backend สามารถเริ่ม Phase 2b ได้ทันที

---

## 5. Database

(ไม่เปลี่ยนแปลงจาก v3 — schema ยังเป็น Phase 2b ที่ยังไม่เริ่ม)

---

## 6. Security Checklist

- [x] Laravel Sanctum (token-based) — Phase 1
- [x] Role-Based Access Control — Phase 1
- [ ] CSRF Protection (สำหรับ session-based endpoints) — ไม่จำเป็นถ้าใช้ Bearer token ตลอด
- [ ] XSS Protection (sanitize input, escape output) — Phase 2b
- [ ] SQL Injection Protection — Phase 2b (coding practice)
- [ ] File Validation (MIME type, ขนาดไฟล์, virus scan ถ้าเป็นไปได้) — Phase 2b
- [ ] Rate Limiting (โดยเฉพาะ endpoint ที่เรียก AI API) — Phase 2b
- [x] **(ใหม่ v4) Network segmentation**: MariaDB/Redis/ChromaDB/MinIO ไม่ publish host port ใน production base file อีกต่อไป — เข้าถึงได้เฉพาะใน internal Docker network
- [x] **(ใหม่ v4) Immutable deployment**: ไม่มี source-code bind mount ใน `docker-compose.yml` (base) แล้ว — โค้ดถูก COPY เข้า image ตอน build เท่านั้น (dev bind-mount ย้ายไป `docker-compose.override.yml`)
- [ ] **(ใหม่ v4, OPEN) MinIO image supply-chain risk** — ดู §11, ต้องตัดสินใจก่อน production launch

---

## 7. Performance Requirements

(ไม่เปลี่ยนแปลงจาก v3) — v4 เพิ่ม: queue worker แยกตาม workload ทำให้ scale CPU-bound (OCR) และ I/O-bound (AI API calls) งานได้อิสระจากกัน (`docker compose --scale queue-worker-ocr=N`)

---

## 8. Deliverables ที่ต้องส่งมอบ

(ไม่เปลี่ยนแปลง) — รายการที่ 9 "Docker Configuration" ✅ เสร็จสมบูรณ์ในรอบนี้
(ดู §9.4), รายการที่ 10 "Deployment Guide" ✅ เสร็จสมบูรณ์ (`DEPLOYMENT.md`)

---

## 9. ข้อสังเกตเชิงสถาปัตยกรรม (Critical Notes)

(หัวข้อ 9.1–9.3 เดิม ไม่เปลี่ยนแปลง — ดูเอกสาร v3)

### 9.4 Phase 2 Infrastructure Implementation (v4, Team U1/DevOps) — ✅ เสร็จสมบูรณ์

**Files created/changed:**
- `docker-compose.yml` — rewritten as a production-safe base (no bind
  mounts, no internal-service host ports, healthcheck + `depends_on:
  condition: service_healthy` on every service)
- `docker-compose.override.yml` — **new.** Auto-loaded dev convenience layer
  (hot-reload bind mounts, vite dev server, debug host ports)
- `docker-compose.prod.yml` — **new.** Optional overlay for logging config
  and resource limits (scalar/map fields only — see file header for why)
- `backend/Dockerfile` — **new.** `php:8.3-fpm-bookworm` + Tesseract
  (`eng+tha`) + ffmpeg + poppler-utils + ghostscript + the PHP extensions
  Laravel 12 needs; shared by `backend` and all 3 queue-worker containers
- `backend/docker/php/custom.ini`, `backend/docker/php-fpm/www.conf`,
  `backend/docker/php-fpm/healthcheck.sh` — **new.** Upload limits raised
  to 500MB, FPM `/ping` + `/status` enabled for healthchecks
- `frontend/Dockerfile` — **new.** Multi-stage: `development` (vite dev
  server, used by the override) / `builder` / `production` (nginx serving
  the static SPA, default target in the base file)
- `frontend/docker/nginx/default.conf` — **new.** Serves the SPA + proxies
  `/api/*` to the internal API gateway (same-origin, no CORS needed)
- `docker/nginx/backend.conf` — **new.** API gateway config; intentionally
  has **no bind-mount dependency** on the Laravel codebase (pure
  fastcgi_pass with a hardcoded `SCRIPT_FILENAME`, since this is a JSON-only
  API with no nginx-served static assets)
- `.env.example`, `backend.env.example.additions.md`,
  `frontend.env.example.additions.md` — Phase 2 environment variables
  (queue names, MinIO/S3 filesystem config, OCR paths, AI provider keys,
  ChromaDB connection)
- `DEPLOYMENT.md` — **new.** Architecture diagram, dev vs. prod run modes,
  healthcheck reference table, scaling guide, backup commands, MinIO risk

**Key architecture decisions made this round:**
1. **Queue workers split by workload shape**, not just duplicated: `ocr`
   (CPU-bound, Tesseract/ffmpeg), `ai` (I/O-bound, external API calls),
   `default` (catch-all). Each is independently scalable.
2. **Single public entrypoint in production**: the `frontend` container
   (nginx + built SPA) is the only service with a published host port and
   internally proxies `/api/*` to the `nginx` API gateway — mirrors the
   existing vite dev-proxy behavior so request paths are identical in dev
   and prod.
3. **No source-code bind mounts in the base file** — moved to
   `docker-compose.override.yml` (auto-loaded in dev only). This is what
   makes the base file deployable as-is on a server.
4. **Pinned every previously-`:latest` image** (`chromadb/chroma`,
   `minio/minio`, `minio/mc`) for build reproducibility. ChromaDB pinned to
   the current 1.x line (`1.5.7`); note the persist path changed from
   `/chroma/chroma` (0.4/0.5.x) to `/data` (1.x) — volume mount updated
   accordingly, and the legacy `IS_PERSISTENT`/`PERSIST_DIRECTORY` env vars
   are intentionally **not** set anymore.
5. **MinIO image risk discovered and flagged, not silently fixed** — see §11.

---

## 10. แผนการพัฒนาที่เสนอ (Proposed Phasing)

| Phase | เนื้อหา |
|---|---|
| 0 | ✅ Project setup: init repo, Docker Compose, env config |
| 1 | ✅ Auth + RBAC + DB Schema หลัก |
| **2a** | ✅ **Infrastructure for Upload + Document Processing Pipeline (this update)** — Docker architecture, OCR/ffmpeg toolchain, queue workers, ChromaDB, healthchecks, deployment docs |
| **2b** | ⏳ **Application code** for the same pipeline (OCR/Whisper/Embedding services, `DocumentController`, upload validation, queue Jobs, ChromaDB client integration) — **not started, ready to begin on top of 2a** |
| 3 | AI Summarization + Flash Cards |
| 4 | Question Generator + Quiz Engine |
| 5 | RAG Chatbot + Quick Answer Mode |
| 6 | Study Planner + Exam Prediction |
| 7 | Analytics + Dashboard + Admin Panel |
| 8 | Security hardening, Performance tuning, Docker/Deployment |

---

## 11. Open Questions / Critical Risks (เหลือที่ยังไม่ confirm)

- [ ] งบประมาณ/Rate limit สำหรับ AI API ต่อเดือนเป็นเท่าไร — env var
      `AI_MONTHLY_BUDGET_USD` เตรียมไว้แล้ว, ค่าจริงรอทีมยืนยัน
- [ ] ระดับความแม่นยำที่ยอมรับได้ของ Tesseract+Thai pack กับเอกสารจริง
      (ยังไม่ทดสอบ — Dockerfile พร้อม `tesseract-ocr-tha` แล้ว ทดสอบได้ทันที)
- [ ] ขนาด/quota ของ MinIO ต่อผู้ใช้
- [ ] **🔴 ใหม่ — CRITICAL: MinIO Docker image supply-chain risk.** MinIO
      community edition ถูกเปลี่ยนเป็น source-only distribution ตั้งแต่ ต.ค. 2025
      และเข้าสถานะ maintenance mode ตั้งแต่ ธ.ค. 2025; image ล่าสุดที่มีบน
      Docker Hub มีช่องโหว่ privilege-escalation ที่ยังไม่ patch
      (GHSA-jjjj-jwhf-8rgr / CVE-2025-62506, CVSS 8.1) ไม่มี tag ใดบน Docker
      Hub ที่ patch แล้ว ทีมต้องตัดสินใจก่อน production launch ระหว่าง: (1)
      self-build MinIO จาก source ที่ patch แล้ว (2) ย้ายไป S3-compatible
      อื่นที่ maintain อยู่ (Garage/SeaweedFS) (3) ใช้ managed cloud storage
      (S3/R2/B2) แทน self-hosted — รายละเอียดเต็มที่ `DEPLOYMENT.md` §6
- [ ] เวอร์ชัน Claude Sonnet ที่จะใช้ — กำหนดไว้แล้วเป็น `claude-sonnet-4-6`
      ใน `backend.env.example.additions.md`, ปรับได้ตามรุ่นที่ Anthropic ออกใหม่

---

*หมายเหตุ: ไฟล์นี้คือเอกสาร "ความจำของโครงการ" ควรอัปเดตทุกครั้งที่มีการตัดสินใจสำคัญ*
