# ENVIRONMENT CHECKLIST
**Project:** AI Study Assistant Platform  
**Version:** Phase 2 Release  
**Team:** U5 Integration Lead  
**Date:** 2026-07-01  

---

## ROOT `.env` — Infrastructure & Docker Compose Variables

| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `FRONTEND_PORT` | Optional | `8080` | `8080` | Host port for public SPA | Use reverse proxy instead; set to `8080` behind TLS terminator |
| `NGINX_PORT` | Optional (dev) | `8000` | `8000` | API gateway host port for dev testing | Do NOT expose in production; omit from prod overlay |
| `MARIADB_DATABASE` | Optional | `study_assistant` | `study_assistant` | Database name | Set explicitly |
| `MARIADB_USER` | Optional | `study_assistant` | `study_assistant` | DB application user | Set explicitly |
| `MARIADB_PASSWORD` | **REQUIRED** | none | `S3cur3P@ss!` | DB application user password | 32+ char random; never commit |
| `MARIADB_ROOT_PASSWORD` | **REQUIRED** | none | `R00tS3cur3!` | MariaDB root password | 32+ char random; never commit |
| `MARIADB_PORT` | Optional (dev) | `3306` | `3306` | Host port for DB access | Omit in production |
| `REDIS_PORT` | Optional (dev) | `6379` | `6379` | Host port for Redis | Omit in production |
| `CHROMA_VERSION` | Optional | `1.5.7` | `1.5.7` | ChromaDB image tag | Pin to `1.5.7` — do NOT use `latest` |
| `CHROMA_PORT` | Optional (dev) | `8001` | `8001` | Host port for ChromaDB | Omit in production |
| `MINIO_VERSION` | Optional | `RELEASE.2025-04-22T22-12-26Z` | same | MinIO image tag | Pin explicitly; see MinIO CVE risk in DEPLOYMENT_GUIDE |
| `MINIO_MC_VERSION` | Optional | `RELEASE.2025-08-13T08-35-41Z-cpuv1` | same | MinIO mc client tag | Pin explicitly |
| `MINIO_ROOT_USER` | **REQUIRED** | none | `minio_admin` | MinIO admin username | Must match `backend/.env` `AWS_ACCESS_KEY_ID` |
| `MINIO_ROOT_PASSWORD` | **REQUIRED** | none | `MinioS3cur3!` | MinIO admin password | Must match `backend/.env` `AWS_SECRET_ACCESS_KEY`; 32+ chars |
| `MINIO_API_PORT` | Optional (dev) | `9000` | `9000` | MinIO API host port | Omit in production |
| `MINIO_CONSOLE_PORT` | Optional (dev) | `9001` | `9001` | MinIO console host port | Omit in production |
| `MINIO_BUCKET_RAW` | Optional | `study-assistant-raw` | `study-assistant-raw` | Raw uploads bucket name | Must match `backend/.env` `MINIO_BUCKET` |
| `MINIO_BUCKET_PROCESSED` | Optional | `study-assistant-processed` | `study-assistant-processed` | Processed files bucket | For future processed derivatives |

---

## `backend/.env` — Laravel Application Variables

### Application Core
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `APP_NAME` | Optional | `Laravel` | `AI Study Assistant` | App name for notifications | Set to product name |
| `APP_ENV` | **REQUIRED** | `local` | `production` | Environment mode | Set to `production` in prod |
| `APP_KEY` | **REQUIRED** | none | `base64:...` | Laravel encryption key | Generate: `php artisan key:generate`; rotate annually |
| `APP_DEBUG` | **REQUIRED** | `true` | `false` | Debug mode | **Must be `false` in production** |
| `APP_URL` | **REQUIRED** | `http://localhost` | `https://yourdomain.com` | Application URL | Set to public HTTPS URL |
| `APP_TIMEZONE` | Optional | `UTC` | `Asia/Bangkok` | App timezone | Set per business requirement |

### Database
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `DB_CONNECTION` | **REQUIRED** | `mysql` | `mariadb` | DB driver | `mariadb` |
| `DB_HOST` | **REQUIRED** | `127.0.0.1` | `mariadb` | DB host | `mariadb` (Docker service name) |
| `DB_PORT` | Optional | `3306` | `3306` | DB port | `3306` |
| `DB_DATABASE` | **REQUIRED** | `laravel` | `study_assistant` | Database name | Must match root `.env` `MARIADB_DATABASE` |
| `DB_USERNAME` | **REQUIRED** | `root` | `study_assistant` | DB user | Must match root `.env` `MARIADB_USER` |
| `DB_PASSWORD` | **REQUIRED** | none | `S3cur3P@ss!` | DB password | Must match root `.env` `MARIADB_PASSWORD` |

### Cache & Session
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `CACHE_STORE` | **REQUIRED** | `file` | `redis` | Cache driver | **Must be `redis`** for AI usage counters |
| `SESSION_DRIVER` | Optional | `file` | `redis` | Session storage | `redis` for multi-container |
| `SESSION_LIFETIME` | Optional | `120` | `120` | Session minutes | Adjust per security policy |
| `REDIS_HOST` | **REQUIRED** | `127.0.0.1` | `redis` | Redis host | `redis` (Docker service name) |
| `REDIS_PASSWORD` | Optional | none | none | Redis auth | Set if Redis has auth enabled |
| `REDIS_PORT` | Optional | `6379` | `6379` | Redis port | `6379` |

### Queue
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `QUEUE_CONNECTION` | **REQUIRED** | `sync` | `redis` | Queue driver | **Must be `redis`** for async processing |
| `REDIS_QUEUE_CONNECTION` | Optional | `default` | `default` | Redis queue connection | `default` |
| `REDIS_QUEUE` | Optional | `default` | `default` | Default queue name | `default` |
| `REDIS_QUEUE_RETRY_AFTER` | Optional | `90` | `90` | Retry timeout (seconds) | `90` |

### Storage / MinIO
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `FILESYSTEM_DISK` | Optional | `local` | `s3` | Default filesystem | `s3` for MinIO |
| `DOCUMENT_STORAGE_DISK` | Optional | `s3` | `s3` | Document storage disk | `s3` |
| `AWS_ACCESS_KEY_ID` | **REQUIRED** | none | `minio_admin` | MinIO access key | Must match root `.env` `MINIO_ROOT_USER` |
| `AWS_SECRET_ACCESS_KEY` | **REQUIRED** | none | `MinioS3cur3!` | MinIO secret key | Must match root `.env` `MINIO_ROOT_PASSWORD` |
| `AWS_DEFAULT_REGION` | Optional | `us-east-1` | `us-east-1` | S3 region | `us-east-1` for MinIO |
| `MINIO_ENDPOINT` | **REQUIRED** | `http://minio:9000` | `http://minio:9000` | MinIO endpoint | `http://minio:9000` (Docker) |
| `MINIO_BUCKET` | **REQUIRED** | `study-assistant-files` | `study-assistant-raw` | Upload bucket | **Must match** root `.env` `MINIO_BUCKET_RAW` |
| `AWS_USE_PATH_STYLE_ENDPOINT` | **REQUIRED** | `true` | `true` | Path-style URLs | **Must be `true`** for MinIO |

### ChromaDB
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `CHROMA_URL` | **REQUIRED** | `http://chromadb:8000` | `http://chromadb:8000` | ChromaDB service URL | `http://chromadb:8000` (Docker) |
| `CHROMA_COLLECTION` | Optional | `study_assistant_docs` | `study_assistant_docs` | Vector collection name | Do NOT change after initial indexing |

### AI — Anthropic (Claude)
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `ANTHROPIC_API_KEY` | **REQUIRED** | none | `sk-ant-api03-...` | Anthropic API key | Rotate every 90 days; store in secrets manager |
| `ANTHROPIC_MODEL` | Optional | `claude-sonnet-4-6` | `claude-sonnet-4-6` | Claude model string | Verify current model availability before deploy |
| `ANTHROPIC_MAX_TOKENS` | Optional | `4096` | `4096` | Max output tokens | Adjust per cost/quality tradeoff |
| `ANTHROPIC_TIMEOUT` | Optional | `120` | `120` | Request timeout (seconds) | `120` minimum for long summaries |
| `ANTHROPIC_MAX_RETRIES` | Optional | `3` | `3` | Retry attempts | `3` |

### AI — OpenAI (Embeddings + Whisper)
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `OPENAI_API_KEY` | **REQUIRED** | none | `sk-proj-...` | OpenAI API key | Rotate every 90 days; store in secrets manager |
| `OPENAI_EMBEDDING_MODEL` | Optional | `text-embedding-3-small` | `text-embedding-3-small` | Embedding model | **Do NOT change** after ChromaDB is populated |
| `OPENAI_WHISPER_MODEL` | Optional | `whisper-1` | `whisper-1` | Transcription model | `whisper-1` |
| `OPENAI_MAX_TOKENS` | Optional | `4096` | `4096` | Max chat tokens | `4096` |
| `OPENAI_TIMEOUT` | Optional | `120` | `120` | Request timeout | `120` |
| `OPENAI_WHISPER_TIMEOUT` | Optional | `300` | `300` | Whisper timeout | `300` — audio files take longer |
| `OPENAI_MAX_RETRIES` | Optional | `3` | `3` | Retry attempts | `3` |

### AI — Provider Selection
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `AI_DEFAULT_CHAT_PROVIDER` | Optional | `claude` | `claude` | Primary AI chat provider | `claude` |

### AI — Budget & Safety
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `AI_BUDGET_CLAUDE_DAILY_TOKENS` | Optional | `1000000` | `500000` | Daily Claude token cap | Set per budget |
| `AI_BUDGET_OPENAI_DAILY_TOKENS` | Optional | `1000000` | `1000000` | Daily OpenAI token cap | Set per budget |
| `AI_BUDGET_ALERT_THRESHOLD_PCT` | Optional | `80` | `80` | Alert at % of budget | `80` |

### RAG Configuration
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `RAG_TOP_K` | Optional | `5` | `5` | Chunks to retrieve per query | `5`; increase for broader context |
| `RAG_SIMILARITY_THRESHOLD` | Optional | `0.75` | `0.75` | Min cosine similarity | `0.75` |
| `RAG_MAX_CONTEXT_TOKENS` | Optional | `3000` | `3000` | Max context window for RAG | `3000` |

### OCR
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `TESSERACT_BIN` | Optional | `/usr/bin/tesseract` | `/usr/bin/tesseract` | Tesseract binary path | Default correct in U1 Docker image |
| `TESSERACT_LANGUAGES` | Optional | `tha+eng` | `tha+eng` | OCR language packs | Must match installed packs in Dockerfile |

### Logging
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `LOG_CHANNEL` | Optional | `stack` | `stack` | Log channel | `stack` |
| `LOG_LEVEL` | Optional | `debug` | `error` | Log level | `error` in production; `debug` in dev |
| `LOG_DEPRECATIONS_CHANNEL` | Optional | `null` | `null` | Deprecation log channel | `null` |

### Mail (Phase 7 feature)
| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `MAIL_MAILER` | Optional | `log` | `smtp` | Mail driver | `log` for dev; configure SMTP for prod |
| `MAIL_HOST` | Optional | `127.0.0.1` | `smtp.mailgun.org` | SMTP host | Set per mail provider |
| `MAIL_PORT` | Optional | `2525` | `587` | SMTP port | `587` |
| `MAIL_FROM_ADDRESS` | Optional | none | `noreply@yourdomain.com` | From address | Set to verified sender |

---

## `frontend/.env` — Vite Build Variables

| Variable | Required | Default | Example | Purpose | Production Recommendation |
|----------|----------|---------|---------|---------|--------------------------|
| `VITE_API_BASE_URL` | Optional | `/api` | `/api` | API base URL for axios client | `/api` — same-origin via nginx proxy |
| `VITE_API_PROXY_TARGET` | Optional (dev) | — | `http://nginx:80` | Vite dev server proxy target | Dev only; not used in production build |
| `VITE_MAX_UPLOAD_MB` | Optional | — | `200` | UI display max upload size | Must be ≤ backend `StoreDocumentRequest` max (200MB) |

---

## PRE-DEPLOYMENT CHECKLIST

### Secrets & Credentials
- [ ] `MARIADB_PASSWORD` set (32+ chars, not default)
- [ ] `MARIADB_ROOT_PASSWORD` set (32+ chars, not default)
- [ ] `MINIO_ROOT_USER` set
- [ ] `MINIO_ROOT_PASSWORD` set (32+ chars, not default)
- [ ] `ANTHROPIC_API_KEY` set and valid
- [ ] `OPENAI_API_KEY` set and valid
- [ ] `APP_KEY` generated (`php artisan key:generate`)
- [ ] All passwords cross-referenced between root `.env` and `backend/.env`

### Configuration
- [ ] `APP_ENV=production` in `backend/.env`
- [ ] `APP_DEBUG=false` in `backend/.env`
- [ ] `CACHE_STORE=redis` in `backend/.env`
- [ ] `QUEUE_CONNECTION=redis` in `backend/.env`
- [ ] `MINIO_BUCKET` matches `MINIO_BUCKET_RAW` in root `.env`
- [ ] `CHROMA_VERSION=1.5.7` pinned (do not use `latest`)
- [ ] `VITE_API_BASE_URL=/api` in `frontend/.env`

### Patches Applied
- [ ] PATCH-01: `backend/bootstrap/app.php` includes `AIServiceProvider`
- [ ] PATCH-02: `ChromaDbService.php` uses `/api/v2/` endpoints
- [ ] PATCH-03: `EmbeddingService.php` includes `embedChunks()` method
- [ ] PATCH-04: `TranscribeAudioJob.php` uses DTO property access
- [ ] PATCH-05: `docker-compose.yml` queue-worker-ai includes `embed` queue
- [ ] PATCH-06: `config/services.php` has aligned API key paths
- [ ] PATCH-07: `ProtectedRoute.jsx` and `MainLayout.jsx` use `role?.slug`

### Infrastructure
- [ ] Docker Engine 24.0+ installed
- [ ] Compose V2 plugin (v2.20+) installed
- [ ] Sufficient disk space (50GB minimum)
- [ ] All required ports available (8080 for production, additional for dev)
- [ ] Network egress to Anthropic API, OpenAI API, Docker Hub confirmed

### Database
- [ ] Migrations run: `php artisan migrate`
- [ ] Seeders run (dev/staging only): `php artisan db:seed`
- [ ] Backup procedure tested

### AI Services
- [ ] Claude API key tested: `curl https://api.anthropic.com/v1/messages ...`
- [ ] OpenAI API key tested: `curl https://api.openai.com/v1/models ...`
- [ ] `php artisan tinker` AI health check passes

### Storage
- [ ] MinIO buckets created by `minio-init` service
- [ ] `study-assistant-raw` bucket exists
- [ ] Test file upload succeeds end-to-end

### Security (Production)
- [ ] `APP_DEBUG=false` confirmed
- [ ] TLS termination configured on reverse proxy
- [ ] MinIO console not exposed publicly
- [ ] Database not exposed publicly
- [ ] Redis not exposed publicly
- [ ] ChromaDB not exposed publicly
- [ ] Review MinIO CVE: GHSA-jjjj-jwhf-8rgr (see DEPLOYMENT_GUIDE.md)

