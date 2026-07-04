# RELEASE_MANIFEST.md

| Field | Value |
|---|---|
| **Team** | U1 (DevOps Lead) |
| **Version** | v4.2 (release packaging) — underlying infra is v4.1 (defect-closed) |
| **Phase** | 2a — Infrastructure only (Phase 2b application code is separate, not started) |
| **Date** | 2026-06-28 |
| **Ready For Merge** | **YES** (see Known Limitations — none are blockers to merging this package; two are blockers to subsequent gates) |

## Files Created (cumulative, v4 → v4.2)

```
infrastructure/docker-compose.yml
infrastructure/docker-compose.override.yml
infrastructure/docker-compose.prod.yml
docker/backend/Dockerfile
docker/backend/php/custom.ini
docker/backend/php-fpm/www.conf
docker/backend/php-fpm/healthcheck.sh
docker/frontend/Dockerfile
docker/frontend/nginx/default.conf
docker/nginx/backend.conf
docker/nginx/frontend.conf
deployment/root/.env.example
deployment/backend/.env.example
deployment/frontend/.env.example
deployment/ENV_VARIABLES.md
scripts/bootstrap-env.sh
docs/DEPLOYMENT.md
docs/PHASE_2_INFRASTRUCTURE.md
docs/IMPLEMENTATION_REPORT.md
docs/VALIDATION_REPORT.md
docs/ACCEPTANCE_REPORT.md
README.md
CHANGELOG.md
RELEASE_MANIFEST.md
```
(`project_memory.md` is modified in place in the target repo, not shipped
as a new file in this package — see "Files Modified.")

## Files Modified (relative to Phase 0/1 baseline)

- `project_memory.md` — versioned to v4.2; decision log, architecture
  diagram (§3), security checklist (§6), phasing table (§10), and open
  risks (§11) updated. `docker-entrypoint.sh` was **reviewed, not
  modified** — it already correctly handles both the pre- and
  post-skeleton-commit states (see Known Limitations).

## Files Removed (superseded)

- `backend.env.example.additions.md`, `frontend.env.example.additions.md`
  (replaced by complete files under `deployment/`).

## Infrastructure Components

| Component | Role |
|---|---|
| `frontend` | nginx, built SPA — sole public entrypoint, proxies `/api/*` internally |
| `nginx` | Internal API gateway, fastcgi → `backend` |
| `backend` | Laravel 12 / PHP-FPM, Tesseract OCR + ffmpeg + poppler baked in |
| `queue-worker-ocr` | CPU-bound queue consumer (`ocr`,`transcribe`) |
| `queue-worker-ai` | I/O-bound queue consumer (`embedding`,`ai-generation`) |
| `queue-worker-default` | Catch-all queue consumer |
| `mariadb` | Relational DB, internal-only |
| `redis` | Cache/queue broker, internal-only |
| `chromadb` | Vector DB (RAG), internal-only, pinned `1.5.7` |
| `minio` / `minio-init` | Object storage, internal-only — **see Known Limitations** |

## Environment Variables

Full table with required/optional/default status and cross-references is
in `deployment/ENV_VARIABLES.md`. Summary by namespace:

- **Root** (`deployment/root/.env.example`): `FRONTEND_PORT`,
  `NGINX_PORT`, `MARIADB_*`, `REDIS_PORT`, `CHROMA_VERSION`,
  `CHROMA_PORT`, `MINIO_*`.
- **Backend** (`deployment/backend/.env.example`, reconstructed):
  standard Laravel vars + `DB_*`, `REDIS_*`, `AWS_*` (S3/MinIO driver),
  `OCR_*`, `FFMPEG_BINARY_PATH`, `CHROMA_HOST`/`CHROMA_PORT`,
  `ANTHROPIC_API_KEY`/`ANTHROPIC_MODEL`, `OPENAI_API_KEY`/
  `OPENAI_EMBEDDING_MODEL`/`OPENAI_WHISPER_MODEL`, `AI_RATE_LIMIT_*`,
  `AI_MONTHLY_BUDGET_USD`.
- **Frontend** (`deployment/frontend/.env.example`, reconstructed):
  `VITE_API_PROXY_TARGET`, `VITE_API_BASE_URL`, `VITE_MAX_UPLOAD_MB`.

`.env` is mandatory in every mode (dev included) — no working fallback
exists by design. Run `scripts/bootstrap-env.sh` once per environment.

## Docker Services

`frontend`, `nginx`, `backend`, `queue-worker-ocr`, `queue-worker-ai`,
`queue-worker-default`, `mariadb`, `redis`, `chromadb`, `minio`,
`minio-init` — 11 services total, defined in
`infrastructure/docker-compose.yml`. Only `frontend` publishes a host
port in the base file; `infrastructure/docker-compose.override.yml` adds
debug ports for the rest in dev only.

## Health Checks

| Service | Check |
|---|---|
| `backend` | `cgi-fcgi` ping to php-fpm's `/ping` |
| `queue-worker-*` (×3) | `pgrep -f 'queue:work'` |
| `nginx` | `wget --spider http://localhost/up` (exercises full fastcgi chain) |
| `frontend` | `wget --spider http://localhost/healthz` (prod) / `:5173/` (dev) |
| `mariadb` | bundled `healthcheck.sh --connect --innodb_initialized` |
| `redis` | `redis-cli ping` |
| `chromadb` | Python `urllib.request` against `/api/v2/heartbeat` (unverified against a live container — see Known Limitations) |
| `minio` | `curl -f http://localhost:9000/minio/health/live` |

## Known Limitations

1. **No live Docker validation was performed** (no daemon, no Debian
   mirror egress in the build environment). All checks are static. See
   `docs/VALIDATION_REPORT.md` §2–3 for exactly what's outstanding.
   **Not a merge blocker; is a blocker before first real deployment.**
2. **`backend/Dockerfile` is not yet fully immutable** — falls back to
   runtime `composer create-project` bootstrap until a Laravel skeleton is
   committed to the target repo. Self-resolving on the next build after
   that commit; no action needed on this package.
3. **MinIO's official image has an unpatched, disclosed CVE
   (GHSA-jjjj-jwhf-8rgr)** and Docker Hub publishing for the community
   edition has been discontinued. Mitigated (internal-only network), not
   fixed. **Escalated to PM — see `docs/DEPLOYMENT.md` §6. Blocker to
   production launch, not to merging this package.**
4. **`backend/.env.example`/`frontend/.env.example` are reconstructed**,
   not verified copies of the real checked-in files. Maintainer merge
   required — see `deployment/ENV_VARIABLES.md` §4.
5. **ChromaDB healthcheck path (`/api/v2/heartbeat`) is unverified**
   against a running `1.5.7` container.

## Runtime Requirements

- Docker Engine + Compose V2 plugin, **v2.20+** recommended (older
  versions still work — `mem_limit`/`cpus` fallbacks cover the resource
  limits that `deploy.resources.limits` might not enforce pre-2.20).
- Outbound network access to: Docker Hub (or a private mirror), a
  Packagist mirror, the npm registry, Anthropic API, OpenAI API.
- Minimum recommended host resources for local dev: 4 CPU / 8GB RAM
  (ChromaDB + MariaDB + Tesseract OCR workers are all memory-hungry under
  load). Production sizing is workload-dependent — see
  `docs/DEPLOYMENT.md` §4 scaling guidance.

## Ready For Merge

**YES.** All four findings from the prior acceptance review are closed
with documented root cause/fix/validation; the previously-missing
`PHASE_2_INFRASTRUCTURE.md` exists; `.env.example` is now a complete
specification with merge instructions; `project_memory.md` is current.
The two items that remain open (MinIO, CI runtime validation) are
correctly classified as **non-blocking to merge** and **blocking to the
next gate** (production launch / first real deployment, respectively) —
not silently resolved, not blocking this package.
