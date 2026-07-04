# PROJECT HANDOFF
**Project:** AI Study Assistant Platform  
**Release:** Phase 2 — Document Upload & Processing Pipeline  
**Integration Lead:** U5  
**Date:** 2026-07-01  
**Handoff To:** Operations / Phase 3 Development Team

---

## 1. ARCHITECTURE SUMMARY

### System Overview
The AI Study Assistant is a multi-tenant, Docker-composed platform designed for Thai language students. It processes documents (PDF, DOCX, images, audio, video) through an AI pipeline, stores vector embeddings in ChromaDB, and serves an AI-powered study assistant via a React SPA.

### Technology Stack
| Layer | Technology | Version |
|-------|-----------|---------|
| Container Orchestration | Docker Compose | V2 |
| API Gateway | Nginx | 1.25-alpine |
| Backend Runtime | PHP-FPM | 8.3 |
| Application Framework | Laravel | 12 |
| Frontend Framework | React | 19 |
| Build Tool | Vite | 8 |
| Primary Database | MariaDB | 11 |
| Cache / Queue Broker | Redis | 7 |
| Vector Database | ChromaDB | 1.5.7 |
| Object Storage | MinIO | RELEASE.2025-04-22 |
| AI Chat | Anthropic Claude | claude-sonnet-4-6 |
| AI Embedding | OpenAI | text-embedding-3-small (1536 dims) |
| AI Transcription | OpenAI Whisper | whisper-1 |
| OCR Engine | Tesseract | 5 (eng+tha) |

### Architecture Pattern
```
Internet → [TLS Reverse Proxy] → frontend:8080 (React SPA)
                                      ↓ /api/* proxy
                              nginx:80 (API Gateway)
                                      ↓
                              php-fpm:9000 (Laravel 12)
                              ↙    ↓    ↓    ↘
                         MariaDB Redis MinIO ChromaDB
                                 ↓
                    ┌────────────┼────────────┐
             worker-default  worker-ocr  worker-ai
             (default queue) (ocr,transcribe) (embed,embedding,ai-generation)
```

---

## 2. DEPLOYMENT SUMMARY

### What Was Deployed
- Full Docker Compose stack (10 services, 3 worker pools)
- Laravel 12 REST API with 26 Phase 2 endpoints
- AI Provider abstraction (Claude + OpenAI + Whisper, Strategy Pattern)
- Document processing pipeline (Upload → OCR/Whisper → Chunk → Embed → ChromaDB)
- React 19 SPA with auth, routing, RBAC, API modules
- Phase 2 database schema (13 tables including Phase 1)

### Integration Patches Applied
7 patches resolved 11 cross-team integration defects. See FINAL_RELEASE_REPORT.md for full patch record.

### Deployment Commands
```bash
# First time
./scripts/bootstrap-env.sh
# Configure all .env files per ENVIRONMENT_CHECKLIST.md
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose exec backend php artisan migrate

# Updates
docker compose pull && docker compose up -d --build
docker compose exec backend php artisan migrate
docker compose restart queue-worker-ocr queue-worker-ai queue-worker-default
```

---

## 3. KNOWN LIMITATIONS (Phase 2)

| Limitation | Detail | Resolution |
|------------|--------|------------|
| No streaming responses | AI chat is non-streamed in Phase 2 scaffold | Phase 5 — SSE implementation |
| Single ChromaDB collection | All users share one collection; isolation via metadata filters | Phase 8 security review |
| No rate limiting on API | AI budget guards exist but no HTTP-level rate limiting | Phase 8 |
| No audit logging | No per-request admin audit trail | Phase 8 |
| No scheduler container | Laravel scheduler must be run via host crontab | Phase 8 |
| No email verification | Email field collected but not verified | Phase 3+ |
| MinIO CVE open | GHSA-jjjj-jwhf-8rgr — internal-only mitigation applied | PM decision required |
| No horizontal scaling config | Single backend container; workers can scale independently | Phase 8 |

---

## 4. DEFERRED FEATURES BY PHASE

### Phase 3 — Content Intelligence
- AI-generated summaries (7 formats: bullet, paragraph, key-points, timeline, concept-map, qa, cornell)
- Automatic flashcard generation from document content
- Google Drive and YouTube URL import
- Email verification flow

### Phase 4 — Assessment Engine
- Quiz generation (5 question types: MCQ, true/false, fill-blank, short-answer, essay)
- Quiz attempt tracking and scoring
- Question bank management

### Phase 5 — AI Chatbot
- RAG-powered chat (ChromaDB retrieval + Claude grounding)
- Chat session history
- Quick answer mode
- SSE streaming responses
- Citation tracking in responses

### Phase 6 — Study Planning
- Personalized study plan generation
- Exam date management
- Progress-based recommendations

### Phase 7 — Analytics
- Study time tracking
- Exam prediction scoring
- Dashboard aggregation API
- Admin analytics panel

### Phase 8 — Production Hardening
- API rate limiting per user/role
- Horizontal scaling configuration
- Containerized Laravel Scheduler
- External APM integration (Datadog/New Relic)
- Audit logging
- Security hardening (CSP headers, RBAC audit)
- MinIO CVE resolution
- Automated backup scheduling

---

## 5. OPERATIONAL NOTES

### Daily Operations
- Monitor queue depth: `docker compose exec redis redis-cli llen queues:embed`
- Monitor failed jobs: `GET /api/admin/jobs?status=failed`
- Monitor AI usage: query `ai_usage_logs` table for daily token consumption
- Log review: `docker compose logs -f --tail=100 queue-worker-ai`

### Queue Health
- Normal: all three worker pools show `Up (healthy)` in `docker compose ps`
- Warning: queue depth > 100 for any queue — investigate worker logs
- Critical: queue-worker-ai down — GenerateEmbeddingsJob will not process

### ChromaDB Notes
- **Do not change `CHROMA_COLLECTION` after initial data is indexed** — all embeddings will be orphaned
- ChromaDB 1.5.7 uses `/api/v2/` API exclusively — confirmed by PATCH-02
- Collection uses cosine similarity for RAG retrieval

### MinIO Notes
- Bucket `study-assistant-raw` stores all user uploads
- Bucket `study-assistant-processed` reserved for future processed derivatives
- Presigned URLs expire after 15 minutes (DocumentStorageService)
- Back up both buckets before any MinIO version upgrade

---

## 6. SUPPORT NOTES

### Common Issues and Resolutions

**Queue jobs not processing:**
1. Check worker status: `docker compose ps queue-worker-*`
2. Check queue depth: `redis-cli llen queues:embed`
3. Check worker logs: `docker compose logs queue-worker-ai`
4. Verify PATCH-05 applied: `grep "embed,embedding" docker-compose.yml`

**ChromaDB 404 errors:**
1. Verify image version: `docker compose exec chromadb python3 -c "import chromadb; print(chromadb.__version__)"`
2. Verify PATCH-02 applied: `grep "api/v2" backend/app/Services/ChromaDbService.php`

**Admin UI not accessible for admin users:**
1. Verify PATCH-07 applied: `grep "role?.slug" frontend/src/app/ProtectedRoute.jsx`
2. Check `/api/auth/me` returns `role: {id, name, slug}` object

**AI providers not responding:**
1. Check API key validity in backend/.env
2. Run health check: `php artisan tinker → app(AIManager::class)->healthCheck()`

### Escalation Path
1. Queue failures → Operations team (OPERATIONS_GUIDE.md)
2. AI provider outages → Anthropic/OpenAI status pages; HasRetry trait handles transient failures
3. Database issues → DBA team; backup procedure in OPERATIONS_GUIDE.md §6
4. Security incidents → Security team; MinIO CVE tracking: GHSA-jjjj-jwhf-8rgr

---

## 7. MAINTENANCE NOTES

### Regular Maintenance Tasks
| Task | Frequency | Command |
|------|-----------|---------|
| Database backup | Daily | See OPERATIONS_GUIDE.md §6 |
| MinIO backup | Daily | See OPERATIONS_GUIDE.md §6 |
| ChromaDB backup | Weekly | See OPERATIONS_GUIDE.md §6 |
| Log review | Daily | `docker compose logs --since 24h` |
| Failed job review | Daily | `GET /api/admin/jobs?status=failed` |
| AI usage review | Weekly | Query `ai_usage_logs` |
| Dependency updates | Monthly | Test in staging first |
| API key rotation | Quarterly | Update both ANTHROPIC_API_KEY and OPENAI_API_KEY |

### Before Any Laravel Update
```bash
docker compose exec backend composer update --dry-run
# Review breaking changes
# Test in staging
docker compose exec backend php artisan migrate --pretend
```

### Before Any ChromaDB Version Update
- Read migration guide for target version
- Backup ChromaDB data volume
- Test /api/v2/ endpoint compatibility — versions beyond 1.5.7 may introduce breaking changes
- Re-verify PATCH-02 endpoints still valid

---

## 8. FUTURE PHASE ROADMAP

```
Phase 2 (CURRENT) ─── Document Processing Pipeline
                           ↓
Phase 3 (Next)    ─── AI Summary + Flashcards + Drive/YouTube Import
                           ↓
Phase 4           ─── Quiz Engine + Assessment
                           ↓
Phase 5           ─── RAG Chatbot + SSE Streaming
                           ↓
Phase 6           ─── Study Planner + Exam Prediction
                           ↓
Phase 7           ─── Analytics + Admin Dashboard
                           ↓
Phase 8           ─── Security Hardening + Scale + Monitoring
```

### Phase 3 Kickoff Prerequisites
- All Phase 2 patches (PATCH-01–07) confirmed merged
- U2 `SummarizationService` already implemented and registered — just needs controller + routes
- U2 `QuestionGenerationService` already implemented — needs Phase 4 controller
- ChromaDB collection populated with Phase 2 embeddings — RAG ready for Phase 5

---

## U5 INTEGRATION LEAD — FINAL STATUS

| Team | Deliverable | Status |
|------|-------------|--------|
| U1 | Infrastructure | ✅ ACCEPTED & INTEGRATED |
| U2 | AI Layer | ✅ ACCEPTED & INTEGRATED |
| U3 | Backend | ✅ ACCEPTED & INTEGRATED |
| U4 | Frontend | ✅ ACCEPTED & INTEGRATED |
| U5 | Integration | ✅ COMPLETE |

**U5 Integration Lead role: CLOSED**  
All integration responsibilities fulfilled. System certified for merge and deployment.

