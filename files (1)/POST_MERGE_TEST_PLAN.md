# POST-MERGE TEST PLAN
**Project:** AI Study Assistant Platform  
**Release:** Phase 2  
**Integration Lead:** U5  
**Date:** 2026-07-01  
**Scope:** Smoke, API, integration, and production sanity tests for the merged U1–U4 system

---

## TEST ENVIRONMENT SETUP

```bash
# Start full stack
docker compose up -d --build

# Confirm all services healthy
docker compose ps   # all (healthy)

# Create test admin and student users via seed or tinker
docker compose exec backend php artisan db:seed --class=TestUserSeeder
# Admin:   admin@test.local   / password
# Student: student@test.local / password
```

---

## SECTION 1 — SMOKE TESTS

| # | Test | Command / Action | Expected Result | Priority |
|---|------|-----------------|----------------|---------|
| S1 | Backend health | `curl http://localhost:8000/up` | HTTP 200 | P0 |
| S2 | Frontend served | `curl http://localhost:8080` | HTTP 200, HTML content | P0 |
| S3 | ChromaDB health | `curl http://localhost:8001/api/v2/heartbeat` | HTTP 200 | P0 |
| S4 | Redis ping | `docker compose exec redis redis-cli ping` | PONG | P0 |
| S5 | MariaDB connect | `docker compose exec mariadb healthcheck.sh --connect` | exit 0 | P0 |
| S6 | MinIO health | `curl http://localhost:9000/minio/health/live` | HTTP 200 | P0 |
| S7 | AI providers | `php artisan tinker → AIManager::healthCheck()` | all true | P0 |
| S8 | Migrations applied | `php artisan migrate:status` | all Ran | P0 |
| S9 | Queue workers running | `docker compose ps queue-worker-*` | all Up (healthy) | P0 |

---

## SECTION 2 — AUTHENTICATION TESTS

| # | Test | Request | Expected | Validates |
|---|------|---------|----------|-----------|
| A1 | Register new user | `POST /api/auth/register {name, email, password, password_confirmation}` | 201, `{token, user}` | Registration flow |
| A2 | Login student | `POST /api/auth/login {email, password}` | 200, `{token, user: {role: {slug: 'student'}}}` | Auth + role object format |
| A3 | Login admin | `POST /api/auth/login` with admin credentials | 200, `{token, user: {role: {slug: 'admin'}}}` | Admin role returned |
| A4 | Get current user | `GET /api/auth/me` with Bearer token | 200, user object with role object | /me endpoint + UserResource |
| A5 | Unauthenticated request | `GET /api/documents` (no token) | 401 | auth:sanctum middleware |
| A6 | Invalid token | `GET /api/documents` with bad token | 401 | Sanctum token validation |
| A7 | Logout | `POST /api/auth/logout` with valid token | 200, token revoked | Token revocation |
| A8 | Post-logout request | `GET /api/auth/me` with revoked token | 401 | Token actually revoked |
| A9 | Wrong password | `POST /api/auth/login` with bad password | 422 or 401 | Credentials validation |

---

## SECTION 3 — RBAC TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| R1 | Student accesses admin route | `GET /api/admin/jobs` with student token | 403 | role:admin middleware |
| R2 | Admin accesses admin route | `GET /api/admin/jobs` with admin token | 200 | Admin bypass in Gate::before |
| R3 | Student accesses own documents | `GET /api/documents` with student token | 200, own docs only | DocumentPolicy viewAny |
| R4 | Student deletes other's document | `DELETE /api/documents/{otherId}` | 403 | DocumentPolicy delete |
| R5 | Admin deletes any document | `DELETE /api/documents/{anyId}` with admin | 200 | Gate::before admin bypass |
| R6 | Frontend ProtectedRoute (admin) | Log in as admin → navigate to /admin | Admin UI visible | PATCH-07 role?.slug |
| R7 | Frontend ProtectedRoute (student) | Log in as student → navigate to /admin | Redirect to /dashboard | PATCH-07 role guard |
| R8 | Admin nav link (admin user) | Log in as admin → inspect MainLayout | Admin link in nav | PATCH-07 nav condition |

---

## SECTION 4 — UPLOAD TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| U1 | Upload PDF | `POST /api/documents` multipart with PDF file | 201, `{document: {id, status: 'pending'}}` | Upload + MinIO storage |
| U2 | Upload image | `POST /api/documents` with JPG | 201 | Image file acceptance |
| U3 | Upload audio | `POST /api/documents` with MP3 | 201 | Audio file acceptance |
| U4 | Upload large file (100MB) | `POST /api/documents` with 100MB PDF | 201 (within 200MB limit) | nginx + php.ini limits |
| U5 | Upload invalid type | `POST /api/documents` with .exe | 422, validation error | StoreDocumentRequest mimes |
| U6 | Verify MinIO storage | After upload: `mc ls local/study-assistant-raw/` | File present | MinIO write |
| U7 | Missing required field | `POST /api/documents` without title | 422, errors.title | StoreDocumentRequest |

---

## SECTION 5 — OCR PIPELINE TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| O1 | PDF OCR triggered | Upload PDF → wait for queue | ProcessingJob status → 'completed' | OcrDocumentJob |
| O2 | Text extracted | `GET /api/documents/{id}` after OCR | `extracted_text` not null | OcrService output |
| O3 | Chunks created | `GET /api/documents/{id}/chunks` | Array of chunk objects | TextChunkerService |
| O4 | Chunk structure | Inspect chunk response | `{id, chunk_index, content, char_start, char_end, chroma_id}` | DocumentChunk model |
| O5 | Thai language OCR | Upload Thai PDF | Thai text extracted correctly | Tesseract tha language |
| O6 | OCR failure handling | Upload corrupt PDF | ProcessingJob status → 'failed', error_message set | OcrDocumentJob failed() |
| O7 | Processing status polling | `GET /api/documents/{id}/status` during processing | Status transitions: pending→processing→completed | ProcessingJobController |

---

## SECTION 6 — WHISPER TRANSCRIPTION TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| W1 | Audio transcription triggered | Upload MP3 → wait for queue | ProcessingJob 'completed' | TranscribeAudioJob |
| W2 | Transcript created | `GET /api/documents/{id}/transcript` | `{content, language, duration_seconds, segments}` | PATCH-04 DTO access |
| W3 | Language detection | Upload Thai audio | `language: 'th'` in transcript | Whisper auto-detection |
| W4 | Duration recorded | Check transcript | `duration_seconds > 0` | TranscriptionResponse.durationSeconds |
| W5 | Text stored on document | `GET /api/documents/{id}` | `extracted_text` matches transcript content | TranscribeAudioJob DB write |

---

## SECTION 7 — EMBEDDING PIPELINE TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| E1 | Embedding job consumed | After OCR → check queue depth | `redis-cli llen queues:embed` → 0 (consumed) | PATCH-05 queue name |
| E2 | Chunks embedded | `GET /api/documents/{id}/chunks` after pipeline | `is_embedded: true` on all chunks | GenerateEmbeddingsJob |
| E3 | ChromaDB populated | After embedding: query ChromaDB collection | Documents present in collection | PATCH-02 + PATCH-03 |
| E4 | Vector dimensions | Inspect ChromaDB stored vector | 1536 dimensions (text-embedding-3-small) | OpenAIEmbeddingProvider |
| E5 | Batch processing | Upload document with 200+ chunks | All chunks embedded in batches of 50 | GenerateEmbeddingsJob batch logic |
| E6 | AI usage logged | Check `ai_usage_logs` table after embedding | Rows inserted with model + token counts | TracksUsage trait |

---

## SECTION 8 — QUEUE TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| Q1 | Default queue worker active | Dispatch ProcessDocumentJob | Job consumed within 5s | queue-worker-default |
| Q2 | OCR queue worker active | OcrDocumentJob dispatched | Consumed by queue-worker-ocr | queue-worker-ocr |
| Q3 | Transcribe queue worker active | TranscribeAudioJob dispatched | Consumed by queue-worker-ocr | queue-worker-ocr (transcribe) |
| Q4 | Embed queue worker active | GenerateEmbeddingsJob dispatched | Consumed by queue-worker-ai | PATCH-05 (embed queue) |
| Q5 | Failed job recorded | Force a job failure (bad API key temporarily) | Row in `failed_jobs` table | Laravel job failure handling |
| Q6 | Failed job API | `GET /api/admin/jobs?status=failed` | Failed jobs listed | ProcessingJobController |
| Q7 | Job retry | `php artisan queue:retry <uuid>` | Job re-queued and processed | Laravel retry mechanism |
| Q8 | Queue depth monitoring | `redis-cli llen queues:default` | Returns integer | Redis connectivity |

---

## SECTION 9 — FRONTEND TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| F1 | SPA loads | Navigate to `http://localhost:8080` | Login page renders | React build + nginx |
| F2 | Login flow | Enter credentials + submit | Redirect to /dashboard, token stored | authStore + authApi |
| F3 | Protected route (auth) | Navigate to /documents without login | Redirect to /login | ProtectedRoute auth check |
| F4 | Protected route (role) | Student navigates to /admin | Redirect to /dashboard | PATCH-07 role?.slug |
| F5 | Admin accesses admin | Admin navigates to /admin | Admin page renders | PATCH-07 + admin route |
| F6 | Admin nav visible | Login as admin → inspect sidebar | Admin link visible in nav | PATCH-07 MainLayout |
| F7 | Admin nav hidden | Login as student → inspect sidebar | No admin link | PATCH-07 MainLayout |
| F8 | API token attached | Open DevTools → inspect request headers | `Authorization: Bearer <token>` on all API calls | axios interceptor |
| F9 | 401 auto-logout | Manually revoke token → trigger any API call | Auto-redirect to /login | axios 401 interceptor |
| F10 | SPA routing fallback | Navigate directly to /dashboard | Page renders (not 404) | nginx try_files |
| F11 | Document list page | Navigate to /documents | Page renders (placeholder) | React Router + placeholder |

---

## SECTION 10 — ADMIN TESTS

| # | Test | Action | Expected | Validates |
|---|------|--------|----------|-----------|
| AD1 | Admin job list | `GET /api/admin/jobs` with admin token | 200, paginated job list | ProcessingJobController admin |
| AD2 | Admin job filter | `GET /api/admin/jobs?status=failed` | Only failed jobs | ProcessingJob status scope |
| AD3 | Non-admin blocked | `GET /api/admin/jobs` with student token | 403 | role:admin middleware |
| AD4 | Admin ping | `GET /api/admin/ping` | 200, `{status: 'ok'}` | Admin route basic health |

---

## SECTION 11 — PRODUCTION SANITY TESTS

Run these after deploying to staging/production environment:

| # | Test | Action | Expected | Priority |
|---|------|--------|----------|---------|
| PS1 | HTTPS only | `curl http://yourdomain.com/api/up` | Redirect to HTTPS | P0 |
| PS2 | No debug info in errors | Trigger 500 → inspect response | No stack trace in response body | P0 |
| PS3 | Sanctum token secure | Inspect response headers | No token in cookie; Bearer only | P0 |
| PS4 | MinIO not public | `curl http://minio:9000` from external | Connection refused | P0 |
| PS5 | Full upload pipeline (prod) | Upload real Thai PDF | OCR → chunk → embed → ChromaDB complete | P0 |
| PS6 | AI provider latency | Time a Claude API call | < 30s for summary request | P1 |
| PS7 | Queue worker resilience | Restart queue-worker-ai | Jobs resume after restart | P1 |
| PS8 | Database backup | Run backup procedure | Restore verified on separate instance | P1 |
| PS9 | Log rotation active | Check after 24h | Logs rotated, no disk exhaustion | P1 |
| PS10 | AI usage tracking | Check `ai_usage_logs` after prod test | Rows present, token counts accurate | P1 |

---

## PASS CRITERIA

All P0 tests must PASS before production launch.  
All P1 tests must PASS before end of first operational week.  
Any test FAILURE must be logged as a defect and triaged before promotion.

