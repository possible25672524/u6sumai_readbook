# DEPLOYMENT GUIDE
**Project:** AI Study Assistant Platform  
**Version:** Phase 2 Release  
**Team:** U5 Integration Lead  
**Date:** 2026-07-01  
**Patches Applied:** PATCH-01 through PATCH-07

---

## PREREQUISITES

### Host Requirements
- Docker Engine 24.0+ with Compose V2 plugin (v2.20+ recommended)
- Minimum 4 CPU cores / 8 GB RAM for local dev
- Minimum 8 CPU cores / 16 GB RAM for production
- 50 GB disk space minimum
- Outbound network: Docker Hub, Packagist, npm registry, Anthropic API, OpenAI API

### External Credentials Required Before Deployment
- `ANTHROPIC_API_KEY` — Anthropic Claude API key
- `OPENAI_API_KEY` — OpenAI API key (embeddings + Whisper)
- All database/storage passwords (see ENVIRONMENT_CHECKLIST.md)

---

## FIRST-TIME SETUP

### Step 1 — Clone and Bootstrap Environment
```bash
git clone <repository> ai-study-assistant
cd ai-study-assistant
./scripts/bootstrap-env.sh   # copies all .env.example → .env
```

### Step 2 — Configure Environment Files
Edit three .env files (see ENVIRONMENT_CHECKLIST.md for all required values):
```bash
# Root .env — infrastructure secrets (DB passwords, MinIO credentials)
nano .env

# Backend .env — Laravel application config
nano backend/.env

# Frontend .env — Vite build config
nano frontend/.env
```

### Step 3 — Apply Integration Patches
The following patched files must be in place before build:
```
backend/bootstrap/app.php              ← PATCH-01 (AIServiceProvider added)
backend/app/Services/ChromaDbService.php ← PATCH-02 (API v2 endpoints)
backend/app/Services/EmbeddingService.php ← PATCH-03 (embedChunks() added)
backend/app/Jobs/TranscribeAudioJob.php  ← PATCH-04 (DTO property access)
infrastructure/docker-compose.yml        ← PATCH-05 (embed queue added)
backend/config/services.php              ← PATCH-06 (config key alignment)
frontend/src/app/ProtectedRoute.jsx      ← PATCH-07 (role.slug comparison)
frontend/src/app/layouts/MainLayout.jsx  ← PATCH-07 (role.slug comparison)
```

### Step 4 — Build and Start (Development)
```bash
# Auto-merges docker-compose.yml + docker-compose.override.yml
docker compose up -d --build

# Wait for all services healthy (may take 2-3 minutes on first build)
docker compose ps
```

### Step 5 — Database Setup
```bash
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed   # dev/staging only
```

### Step 6 — Verify Health
```bash
# All services should show (healthy)
docker compose ps

# Test backend
curl http://localhost:8000/up   # → {"status":"ok"}

# Test AI layer
docker compose exec backend php artisan tinker --execute="app(App\Services\AI\AIManager::class)->healthCheck()"
```

---

## PRODUCTION DEPLOYMENT

### Step 1 — Environment Hardening
```bash
# Do NOT use docker-compose.override.yml in production
# Use production overlay instead:
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

### Step 2 — Key Differences from Dev
| Setting | Dev | Production |
|---------|-----|-----------|
| Source bind mounts | Yes (hot reload) | No (immutable images) |
| Debug mode | `APP_DEBUG=true` | `APP_DEBUG=false` |
| Host ports | All services exposed | Only frontend (:8080) |
| Queue workers | Single default | 3 named pools |
| Resource limits | None | `mem_limit` + `cpus` set |
| Logging | stdout | json-file with rotation |

### Step 3 — SSL/TLS
Add a TLS-terminating reverse proxy (Caddy/Traefik/cloud LB) in front of port 8080.
The application itself does not terminate TLS.

### Step 4 — Scaling Queue Workers
```bash
# Scale OCR workers for high document volume
docker compose -f docker-compose.yml -f docker-compose.prod.yml \
  up -d --scale queue-worker-ocr=3 --scale queue-worker-ai=2
```

---

## SERVICE STARTUP ORDER
Managed automatically by `depends_on: condition: service_healthy`:
```
MariaDB (healthy) ──┐
Redis (healthy)   ──┤
ChromaDB (healthy)──┤──► backend (healthy) ──► queue-workers
MinIO (healthy)   ──┘                      └──► nginx ──► frontend
```

---

## QUEUE WORKER CONFIGURATION (Post PATCH-05)
| Worker | Queues | Timeout | Purpose |
|--------|--------|---------|---------|
| queue-worker-ocr | `ocr,transcribe` | 900s | Tesseract OCR, ffmpeg extraction |
| queue-worker-ai | `embed,embedding,ai-generation` | 300s | OpenAI Embedding, Claude generation |
| queue-worker-default | `default` | 120s | Orchestration, notifications |

---

## HEALTH CHECK ENDPOINTS
| Service | Check | Expected |
|---------|-------|---------|
| Laravel | `GET /up` | HTTP 200 |
| php-fpm | cgi-fcgi ping `/ping` | `pong` |
| MariaDB | `healthcheck.sh --connect` | exit 0 |
| Redis | `redis-cli ping` | `PONG` |
| ChromaDB | `GET /api/v2/heartbeat` | HTTP 200 |
| MinIO | `GET /minio/health/live` | HTTP 200 |
| Frontend | `GET /healthz` | `ok` |

---

## ROLLBACK PROCEDURE
```bash
# Stop and remove containers (preserves volumes)
docker compose down

# Restore previous image tags in docker-compose.yml
# Restart
docker compose up -d

# If database migration needs rollback:
docker compose exec backend php artisan migrate:rollback
```

