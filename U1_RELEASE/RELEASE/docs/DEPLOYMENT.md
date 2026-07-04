# DEPLOYMENT.md — Phase 2 Infrastructure

Status: Phase 2 **infrastructure** (this document) is complete. The Phase 2
**application code** (OCR/Whisper/Embedding services, upload controllers,
document-processing jobs) is a separate, not-yet-started workstream — see
`project_memory.md` §10 for the split.

---

## 1. Architecture at a glance

```
                         ┌────────────────────────┐
   Internet  ───────────▶│  frontend (nginx, SPA) │  <- ONLY public port
                         │  serves React build     │
                         │  proxies /api/* ────────┼────────┐
                         └────────────────────────┘        │
                                                            ▼
                                                 ┌────────────────────┐
                                                 │ nginx (API gateway) │  internal only
                                                 │ fastcgi -> backend  │
                                                 └─────────┬──────────┘
                                                           ▼
                                                 ┌────────────────────┐
                                                 │ backend (php-fpm)   │
                                                 │ Tesseract+ffmpeg+   │
                                                 │ poppler baked in    │
                                                 └─────┬──────┬───────┘
                              ┌───────────────────────┘      └───────────────┐
                              ▼                                              ▼
                 ┌─────────────────────────┐                     ┌─────────────────────┐
                 │ queue-worker-ocr         │                     │ queue-worker-ai      │
                 │ (Tesseract OCR, ffmpeg)  │                     │ (Whisper/OpenAI/     │
                 │ CPU-bound, scale by CPU  │                     │  Claude Sonnet calls)│
                 └─────────────────────────┘                     └─────────────────────┘
                              +  queue-worker-default (catch-all, low priority)

      MariaDB · Redis · ChromaDB · MinIO  — internal Docker network only, no host ports
```

All internal services communicate over the single `study-ai` bridge network
by service name (`mariadb`, `redis`, `chromadb`, `minio`, `backend`, `nginx`).

---

## 2. Two ways to run this, one set of files

| Mode | Command | What you get |
|---|---|---|
| **Local dev** | `docker compose up -d --build` | `docker-compose.yml` + `docker-compose.override.yml` auto-merged: hot-reload bind mounts, vite dev server on :5173, direct host ports on MariaDB/Redis/ChromaDB/MinIO/API-gateway for GUI tools. |
| **Production / staging** | `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build` | Only the base file (immutable images, no bind mounts) + the prod overlay (logging, resource limits, `restart: always`). Only `frontend` publishes a host port. |

**Do not** combine `docker-compose.override.yml` with a production deploy —
delete it or rename it on the server, otherwise Compose auto-loads it and
you'll get dev bind-mounts in production.

First run, either mode:
```bash
./scripts/bootstrap-env.sh               # copies .env.example -> .env in all 3 locations if missing
# now EDIT .env, backend/.env, frontend/.env — see ENV_VARIABLES.md for the
# full variable spec and the cross-references you must keep in sync
# (e.g. root MARIADB_PASSWORD must equal backend/.env's DB_PASSWORD)

docker compose up -d --build
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed   # dev/staging only
```

> v4.1 note: `.env` is mandatory in every mode including local dev — there
> is no compose-level fallback for secrets, by design. See
> `PHASE_2_INFRASTRUCTURE.md` §6 "Defect 1" for why an earlier draft of
> this looked like it had one and didn't actually work.

---

## 3. Health checks — what's checked and how

| Service | Mechanism | Notes |
|---|---|---|
| `backend` | `cgi-fcgi` ping to php-fpm's `/ping` page (FastCGI, no HTTP needed) | Verifies FPM master+worker are alive, independent of nginx |
| `queue-worker-*` | `pgrep -f 'queue:work'` | Process-liveness only. For real job-level monitoring, add Laravel Horizon in a later phase (see §6) |
| `nginx` (API gateway) | `wget --spider http://localhost/up` | Exercises the full chain: nginx → fastcgi → backend → Laravel's built-in `/up` route |
| `frontend` | `wget --spider http://localhost/healthz` (prod) / `http://localhost:5173/` (dev) | |
| `mariadb` | bundled `healthcheck.sh --connect --innodb_initialized` | Unchanged from Phase 1 |
| `redis` | `redis-cli ping` | |
| `chromadb` | `python3 -c "urllib.request.urlopen(.../api/v2/heartbeat)"` | Uses Python already inside the Chroma image — no extra package needed |
| `minio` | `curl -f http://localhost:9000/minio/health/live` | Unchanged from Phase 1 |

Verify everything is healthy:
```bash
docker compose ps              # STATUS column shows (healthy)/(unhealthy)
docker compose logs -f backend queue-worker-ocr queue-worker-ai
```

`depends_on: condition: service_healthy` is used everywhere a real
dependency exists (e.g. `backend` won't start until MariaDB/Redis/
ChromaDB/MinIO are healthy; queue workers wait on `backend`), so a cold
`docker compose up` comes up in the correct order automatically.

---

## 4. Scaling queue workers

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml \
  up -d --scale queue-worker-ocr=3 --scale queue-worker-ai=2
```

Guidance:
- `queue-worker-ocr` is CPU-bound (Tesseract, ffmpeg) → scale with available
  CPU cores; resource limits are capped at 2 CPU/1GB per replica in the base
  file, adjust in `docker-compose.prod.yml` for your hardware.
- `queue-worker-ai` is I/O-bound (waiting on Anthropic/OpenAI APIs) → more
  replicas help even on modest CPU, but watch `AI_MONTHLY_BUDGET_USD`.
- `queue-worker-default` rarely needs more than 1 replica.

---

## 5. Uploads & timeouts (Module 3/4)

Raised everywhere together — if you change one, change all three:

| Layer | Setting | Value |
|---|---|---|
| `backend/docker/php/custom.ini` | `upload_max_filesize` / `post_max_size` | 500M / 520M |
| `docker/nginx/backend.conf` | `client_max_body_size` | 500m |
| `frontend/docker/nginx/default.conf` | `client_max_body_size` | 500m |

Actual OCR/transcription/embedding work happens **asynchronously** in the
queue workers (`--timeout=900` for OCR, `--timeout=300` for AI calls), so
the web-facing timeouts above only need to cover the upload itself, not the
processing.

---

## 6. Known critical risk: MinIO

MinIO's community edition has effectively been discontinued as a maintained
Docker image:

- Since October 2025, MinIO stopped publishing new Docker Hub images and
  moved the community edition to source-only distribution; as of December
  2025 the open-source project is in maintenance mode (no further features).
- The last image available on Docker Hub carries an unpatched, **high
  severity (CVSS 8.1)** privilege-escalation advisory:
  **GHSA-jjjj-jwhf-8rgr / CVE-2025-62506** (service-account / STS session
  policy bypass). No currently published `minio/minio` tag on Docker Hub is
  free of it.

**This affects `project_memory.md`'s confirmed Storage = MinIO decision.**
We have NOT silently replaced it — that is a product/architecture decision
for the team, not something DevOps should decide unilaterally. For now:

- The pinned tag in `docker-compose.yml` (`RELEASE.2025-04-22T22-12-26Z`) is
  chosen **only** for build reproducibility (no `:latest`), not because it
  is patched.
- Mitigations already applied in this infra: MinIO has no host port in the
  base file (internal-only), only trusted backend/queue-worker containers
  hold credentials, and no STS/service-account features are configured.
- **Before any public/production launch**, the team should pick one of:
  1. Build a patched MinIO image from source (`git checkout` the fix commit
     + `make docker`) and host it in a private registry.
  2. Migrate to a actively-maintained S3-compatible alternative (e.g.
     Garage, SeaweedFS) — bigger change, evaluate storage API compatibility
     with Laravel's S3 filesystem driver first.
  3. Move to a managed S3-compatible service (AWS S3, Cloudflare R2,
     Backblaze B2) and drop self-hosted object storage entirely.

This is tracked as an open item in `project_memory.md` §11.

---

## 7. Backups (manual, until Phase 7's Admin Panel automates this)

```bash
# MariaDB
docker compose exec mariadb sh -c \
  'mysqldump -u root -p"$MARIADB_ROOT_PASSWORD" study_assistant' > backup-$(date +%F).sql

# MinIO (mirror both buckets to a local folder)
docker compose run --rm minio-init sh -c \
  'mc mirror local/study-assistant-raw /backup/raw && mc mirror local/study-assistant-processed /backup/processed'
```

---

## 8. Suggested next steps (not implemented in Phase 2 infra)

- Laravel Horizon for real queue dashboards/metrics (current healthchecks
  are liveness-only, not throughput/failure-rate aware).
- A TLS-terminating reverse proxy / load balancer (Caddy, Traefik, or your
  cloud provider's LB) in front of the `frontend` container for HTTPS —
  intentionally out of scope here since certificate management is
  environment-specific.
- Resolve the MinIO risk above before go-live.
