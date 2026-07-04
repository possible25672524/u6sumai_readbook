# MERGE CHECKLIST
**Project:** AI Study Assistant Platform  
**Release:** Phase 2  
**Integration Lead:** U5  
**Date:** 2026-07-01  
**Pre-condition:** PATCH-01 through PATCH-07 must be applied before merge

---

## INSTRUCTIONS
Complete every item in order. Mark each item PASS or FAIL before proceeding to the next section. Do not proceed to production deployment until every item is PASS.

---

## SECTION 1 — REPOSITORY STRUCTURE

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 1.1 | `backend/bootstrap/app.php` contains `AIServiceProvider::class` | Present in withProviders() | ☐ PASS / FAIL |
| 1.2 | `backend/app/Services/ChromaDbService.php` uses `/api/v2/` | No `/api/v1/` references remain | ☐ PASS / FAIL |
| 1.3 | `backend/app/Services/EmbeddingService.php` contains `embedChunks(Collection $chunks)` | Method exists | ☐ PASS / FAIL |
| 1.4 | `backend/app/Jobs/TranscribeAudioJob.php` uses `$result->text` (not `$result['text']`) | DTO property access | ☐ PASS / FAIL |
| 1.5 | `frontend/src/app/ProtectedRoute.jsx` uses `user?.role?.slug` | Not `user?.role` | ☐ PASS / FAIL |
| 1.6 | `frontend/src/app/layouts/MainLayout.jsx` uses `user?.role?.slug === 'admin'` | Not `user?.role === 'admin'` | ☐ PASS / FAIL |
| 1.7 | No duplicate `EmbeddingService.php` (only one file at `app/Services/EmbeddingService.php`) | Single canonical file | ☐ PASS / FAIL |
| 1.8 | No duplicate `TranscriptionService.php` with direct HTTP calls | U2 AIManager-based version is canonical | ☐ PASS / FAIL |
| 1.9 | `backend/config/services.php` contains both `key` and `api_key` under `openai` section | PATCH-06 applied | ☐ PASS / FAIL |
| 1.10 | All Phase 2 migration files present (000010–000017 + 230621) | 9 migration files | ☐ PASS / FAIL |

---

## SECTION 2 — DOCKER VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 2.1 | `docker-compose.yml` queue-worker-ai command includes `embed` | `--queue=embed,embedding,ai-generation` | ☐ PASS / FAIL |
| 2.2 | `chromadb/chroma` image pinned to `1.5.7` | Not `latest` | ☐ PASS / FAIL |
| 2.3 | ChromaDB healthcheck uses `/api/v2/heartbeat` | Not `/api/v1/heartbeat` | ☐ PASS / FAIL |
| 2.4 | MinIO image pinned to specific release tag | Not `latest` | ☐ PASS / FAIL |
| 2.5 | `minio-init` creates both `study-assistant-raw` and `study-assistant-processed` buckets | Two buckets | ☐ PASS / FAIL |
| 2.6 | `frontend` is the only service with a published host port in base file | Only port 8080 | ☐ PASS / FAIL |
| 2.7 | All services have `healthcheck` defined | 8 services with checks | ☐ PASS / FAIL |
| 2.8 | `depends_on: condition: service_healthy` used for all dependent services | Not `condition: service_started` | ☐ PASS / FAIL |
| 2.9 | `queue-worker-ocr` command includes `ocr,transcribe` queues | Correct queue list | ☐ PASS / FAIL |
| 2.10 | `queue-worker-default` command includes `default` queue | Correct queue list | ☐ PASS / FAIL |
| 2.11 | `docker compose build` completes without errors | Exit code 0 | ☐ PASS / FAIL |
| 2.12 | `docker compose up -d` all services reach healthy state within 3 minutes | All `(healthy)` in `docker compose ps` | ☐ PASS / FAIL |

---

## SECTION 3 — LARAVEL VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 3.1 | `php artisan config:clear && php artisan config:cache` runs without error | Exit code 0 | ☐ PASS / FAIL |
| 3.2 | `php artisan route:list` shows all 26+ Phase 2 routes | Routes registered | ☐ PASS / FAIL |
| 3.3 | `php artisan migrate --pretend` shows all migrations | No errors | ☐ PASS / FAIL |
| 3.4 | `php artisan migrate` runs without error on fresh database | Exit code 0 | ☐ PASS / FAIL |
| 3.5 | `php artisan queue:work --queue=embed --once` processes a test job | Job consumed | ☐ PASS / FAIL |
| 3.6 | `GET /up` returns HTTP 200 | `{"status":"ok"}` or similar | ☐ PASS / FAIL |
| 3.7 | `AIManager::class` resolves from container | No binding exception | ☐ PASS / FAIL |
| 3.8 | `AIServiceProvider` boot() completes without exception | All 12 singletons bound | ☐ PASS / FAIL |
| 3.9 | `DocumentPolicy` registered via `Gate::policy()` | `$this->authorize()` works | ☐ PASS / FAIL |
| 3.10 | `EventServiceProvider` registered; listeners mapped | Events fire correctly | ☐ PASS / FAIL |
| 3.11 | `APP_KEY` set and valid (base64 encoded) | Not empty or default | ☐ PASS / FAIL |
| 3.12 | `APP_DEBUG=false` in production environment | Verified in prod .env | ☐ PASS / FAIL |

---

## SECTION 4 — REACT / VITE FRONTEND VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 4.1 | `npm run build` completes without errors | Exit code 0, `dist/` created | ☐ PASS / FAIL |
| 4.2 | `ProtectedRoute.jsx` uses `user?.role?.slug` comparison | PATCH-07 applied | ☐ PASS / FAIL |
| 4.3 | `MainLayout.jsx` uses `user?.role?.slug === 'admin'` | PATCH-07 applied | ☐ PASS / FAIL |
| 4.4 | `VITE_API_BASE_URL=/api` in `frontend/.env` | Production value | ☐ PASS / FAIL |
| 4.5 | `apiClient` baseURL defaults to `/api` | Same-origin routing | ☐ PASS / FAIL |
| 4.6 | Bearer token attached to all authenticated requests | Interceptor verified | ☐ PASS / FAIL |
| 4.7 | 401 response triggers logout and redirect to `/login` | Interceptor verified | ☐ PASS / FAIL |
| 4.8 | Frontend served at `http://localhost:8080` | Correct port | ☐ PASS / FAIL |
| 4.9 | SPA routing fallback configured in nginx (`try_files $uri /index.html`) | React Router works | ☐ PASS / FAIL |

---

## SECTION 5 — ENVIRONMENT VARIABLES VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 5.1 | `MARIADB_PASSWORD` set (not empty or default) | 32+ char random value | ☐ PASS / FAIL |
| 5.2 | `MARIADB_ROOT_PASSWORD` set (not empty or default) | 32+ char random value | ☐ PASS / FAIL |
| 5.3 | `MINIO_ROOT_USER` set | Non-default value | ☐ PASS / FAIL |
| 5.4 | `MINIO_ROOT_PASSWORD` set (not empty or default) | 32+ char random value | ☐ PASS / FAIL |
| 5.5 | `ANTHROPIC_API_KEY` set and begins with `sk-ant-` | Valid key format | ☐ PASS / FAIL |
| 5.6 | `OPENAI_API_KEY` set and begins with `sk-` | Valid key format | ☐ PASS / FAIL |
| 5.7 | `AWS_ACCESS_KEY_ID` matches `MINIO_ROOT_USER` | Cross-reference check | ☐ PASS / FAIL |
| 5.8 | `AWS_SECRET_ACCESS_KEY` matches `MINIO_ROOT_PASSWORD` | Cross-reference check | ☐ PASS / FAIL |
| 5.9 | `MINIO_BUCKET` matches `MINIO_BUCKET_RAW` from root `.env` | Same bucket name | ☐ PASS / FAIL |
| 5.10 | `CACHE_STORE=redis` in backend `.env` | Not `file` | ☐ PASS / FAIL |
| 5.11 | `QUEUE_CONNECTION=redis` in backend `.env` | Not `sync` | ☐ PASS / FAIL |
| 5.12 | `REDIS_HOST=redis` in backend `.env` | Docker service name | ☐ PASS / FAIL |
| 5.13 | `DB_HOST=mariadb` in backend `.env` | Docker service name | ☐ PASS / FAIL |
| 5.14 | `MINIO_ENDPOINT=http://minio:9000` in backend `.env` | Docker service name | ☐ PASS / FAIL |
| 5.15 | `CHROMA_URL=http://chromadb:8000` in backend `.env` | Docker service name | ☐ PASS / FAIL |
| 5.16 | `AWS_USE_PATH_STYLE_ENDPOINT=true` in backend `.env` | Required for MinIO | ☐ PASS / FAIL |

---

## SECTION 6 — AI PROVIDER VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 6.1 | `AIManager::healthCheck()` returns `['chat:claude' => true]` | Claude reachable | ☐ PASS / FAIL |
| 6.2 | `AIManager::healthCheck()` returns `['chat:openai' => true]` | OpenAI reachable | ☐ PASS / FAIL |
| 6.3 | `AIManager::healthCheck()` returns `['embedding' => true]` | Embedding reachable | ☐ PASS / FAIL |
| 6.4 | `AIManager::embed('test')` returns 1536-dimension vector | Correct dimensions | ☐ PASS / FAIL |
| 6.5 | `AIManager::transcribe()` test with sample audio file succeeds | TranscriptionResponse returned | ☐ PASS / FAIL |
| 6.6 | `SummarizationService::summarize($text, 'bullet')` returns ChatResponse | No exception | ☐ PASS / FAIL |
| 6.7 | `ANTHROPIC_MODEL` env value is a valid, currently-available model string | Verify with Anthropic docs | ☐ PASS / FAIL |

---

## SECTION 7 — CHROMADB VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 7.1 | `GET http://chromadb:8000/api/v2/heartbeat` returns HTTP 200 | v2 API working | ☐ PASS / FAIL |
| 7.2 | `ChromaDbService::getOrCreateCollection()` returns collection UUID | No exception | ☐ PASS / FAIL |
| 7.3 | `ChromaDbService::upsert()` with test vectors succeeds | HTTP 200 from ChromaDB | ☐ PASS / FAIL |
| 7.4 | `ChromaDbService::query()` returns results | No 404 error | ☐ PASS / FAIL |
| 7.5 | `CHROMA_COLLECTION` name consistent across all deployments | Do not change after first index | ☐ PASS / FAIL |

---

## SECTION 8 — REDIS VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 8.1 | `redis-cli ping` returns `PONG` | Redis healthy | ☐ PASS / FAIL |
| 8.2 | `Cache::increment('test_key')` succeeds via Laravel | No exception | ☐ PASS / FAIL |
| 8.3 | Queue jobs visible in Redis after dispatch | `redis-cli llen queues:default` > 0 after dispatch | ☐ PASS / FAIL |
| 8.4 | Queue workers consume jobs from Redis | Jobs disappear from queue after processing | ☐ PASS / FAIL |

---

## SECTION 9 — MINIO VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 9.1 | `GET http://minio:9000/minio/health/live` returns HTTP 200 | MinIO healthy | ☐ PASS / FAIL |
| 9.2 | `study-assistant-raw` bucket exists | Created by minio-init | ☐ PASS / FAIL |
| 9.3 | `study-assistant-processed` bucket exists | Created by minio-init | ☐ PASS / FAIL |
| 9.4 | File upload via `DocumentStorageService::store()` succeeds | File visible in MinIO | ☐ PASS / FAIL |
| 9.5 | Presigned URL generation works | URL valid for 15 minutes | ☐ PASS / FAIL |
| 9.6 | `DocumentStorageService::downloadToTemp()` retrieves file | Temp file created | ☐ PASS / FAIL |

---

## SECTION 10 — DATABASE VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 10.1 | All 13 Phase 2 tables exist after migration | `SHOW TABLES` output verified | ☐ PASS / FAIL |
| 10.2 | Foreign key constraints active | `PRAGMA foreign_keys` or MariaDB equivalent | ☐ PASS / FAIL |
| 10.3 | `ai_usage_logs` table exists with correct columns | 9 columns per migration | ☐ PASS / FAIL |
| 10.4 | `document_chunks.chroma_id` is nullable and indexed | Per migration schema | ☐ PASS / FAIL |
| 10.5 | `failed_jobs` table exists | Queue failure tracking | ☐ PASS / FAIL |
| 10.6 | `personal_access_tokens` table exists (Phase 1) | Sanctum dependency | ☐ PASS / FAIL |
| 10.7 | `roles` and `permissions` tables exist (Phase 1) | RBAC dependency | ☐ PASS / FAIL |

---

## SECTION 11 — SECURITY VALIDATION

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 11.1 | `APP_DEBUG=false` in production | No stack traces in responses | ☐ PASS / FAIL |
| 11.2 | No default passwords in production `.env` | All secrets replaced | ☐ PASS / FAIL |
| 11.3 | MinIO not accessible from public internet | Internal Docker network only | ☐ PASS / FAIL |
| 11.4 | MariaDB not accessible from public internet | Internal Docker network only | ☐ PASS / FAIL |
| 11.5 | Redis not accessible from public internet | Internal Docker network only | ☐ PASS / FAIL |
| 11.6 | ChromaDB not accessible from public internet | Internal Docker network only | ☐ PASS / FAIL |
| 11.7 | TLS termination configured on reverse proxy | HTTPS only for public access | ☐ PASS / FAIL |
| 11.8 | `file_path` not returned in Document API responses | Hidden in DocumentResource | ☐ PASS / FAIL |
| 11.9 | Admin routes protected by `role:admin` middleware | Verified via route:list | ☐ PASS / FAIL |
| 11.10 | Sanctum tokens revoked on logout | `personal_access_tokens` record deleted | ☐ PASS / FAIL |

---

## SECTION 12 — DOCUMENTATION VALIDATION

| # | Document | Status |
|---|----------|--------|
| 12.1 | DEPLOYMENT_GUIDE.md | ☐ PASS / FAIL |
| 12.2 | OPERATIONS_GUIDE.md | ☐ PASS / FAIL |
| 12.3 | ENVIRONMENT_CHECKLIST.md | ☐ PASS / FAIL |
| 12.4 | END_TO_END_REPORT.md | ☐ PASS / FAIL |
| 12.5 | SYSTEM_ACCEPTANCE_REPORT.md | ☐ PASS / FAIL |
| 12.6 | FINAL_RELEASE_REPORT.md | ☐ PASS / FAIL |
| 12.7 | MERGE_CHECKLIST.md (this document) | ☐ PASS / FAIL |
| 12.8 | READY_FOR_DEPLOYMENT.md | ☐ PASS / FAIL |

---

## SECTION 13 — FINAL MERGE GATE

All items below must be PASS before merging to main/production branch.

| # | Gate Condition | Status |
|---|---------------|--------|
| 13.1 | All PATCH-01 through PATCH-07 applied and verified | ☐ PASS / FAIL |
| 13.2 | All Section 1–12 checks PASS | ☐ PASS / FAIL |
| 13.3 | `docker compose build` succeeds from clean checkout | ☐ PASS / FAIL |
| 13.4 | `docker compose up -d` all services healthy | ☐ PASS / FAIL |
| 13.5 | `php artisan migrate` succeeds on fresh DB | ☐ PASS / FAIL |
| 13.6 | Full document upload → OCR → embed → ChromaDB flow tested | ☐ PASS / FAIL |
| 13.7 | Admin user can access /admin pages (PATCH-07 verified) | ☐ PASS / FAIL |
| 13.8 | No `.env` files committed to repository | ☐ PASS / FAIL |
| 13.9 | `.env.example` files up to date | ☐ PASS / FAIL |
| 13.10 | MinIO CVE risk accepted by PM/stakeholders | ☐ PASS / FAIL |

---

## ROLLBACK PROCEDURE

If merge must be reverted:
```bash
# 1. Stop services
docker compose down

# 2. Revert to previous Git tag
git checkout <previous-release-tag>

# 3. Restore database from backup taken pre-merge
cat pre-merge-backup.sql | docker compose exec -T mariadb \
  mysql -u root -p"$MARIADB_ROOT_PASSWORD" study_assistant

# 4. Restart on previous version
docker compose up -d --build

# 5. Verify health
docker compose ps
curl http://localhost:8000/up
```

