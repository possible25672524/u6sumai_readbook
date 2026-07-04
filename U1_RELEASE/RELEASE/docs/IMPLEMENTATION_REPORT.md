# IMPLEMENTATION_REPORT.md — U1 Infrastructure (Phase 2a)

## 1. Objective

Provide the Docker infrastructure required for Phase 2's Document
Processing Pipeline (OCR, audio/video transcription pre-processing,
embeddings, vector retrieval) on top of the existing Phase 0/1
Auth+RBAC foundation, without implementing any of the pipeline's
application logic (that's Phase 2b, a separate, not-yet-started
workstream).

## 2. What was built

### 2.1 Orchestration (`infrastructure/`)
A single production-safe base (`docker-compose.yml`) plus two layered
files instead of one dev-only file:
- `docker-compose.override.yml` — auto-loaded dev convenience (source
  bind-mounts, vite dev server, debug host ports). Never deployed
  intentionally to a real server.
- `docker-compose.prod.yml` — optional overlay for logging config and
  resource limits (scalar/map fields only — Compose concatenates list
  fields like `ports`/`volumes` across files, so port-hiding and
  bind-mount-removal had to live in the base file's design, not in an
  override trying to subtract from it).

### 2.2 Images (`docker/`)
- `backend/Dockerfile`: one image shared by the API container and three
  queue-worker containers (`ocr`, `ai`, `default`), differing only by
  `command:`. Bakes in Tesseract OCR (`eng+tha`), ffmpeg, poppler-utils,
  ghostscript, and the PHP extensions Laravel 12 needs. Conditionally
  runs `composer install` at build time if `composer.json` exists yet
  (it doesn't, as of this delivery — see §4).
- `frontend/Dockerfile`: multi-stage (`development` / `builder` /
  `production`). The production stage serves the built SPA via nginx and
  proxies `/api/*` internally — making `frontend` the **only** container
  that needs a published host port in production.
- `docker/nginx/backend.conf`: the internal API gateway. Deliberately has
  no dependency on a mounted copy of the Laravel codebase — this is a
  pure JSON API, so every request is forwarded to php-fpm via a hardcoded
  `SCRIPT_FILENAME`, removing an entire class of "nginx can't see the
  code" deployment bugs.

### 2.3 Queue architecture
Split by workload shape rather than duplicated generically:
- `queue-worker-ocr`: CPU-bound (Tesseract, ffmpeg), `--timeout=900`.
- `queue-worker-ai`: I/O-bound (Whisper/OpenAI/Claude calls), `--timeout=300`.
- `queue-worker-default`: catch-all.
Each scales independently (`docker compose --scale queue-worker-ocr=N`).

### 2.4 Health checks
Every service has one, each using tooling already inside that image
(php-fpm's own `/ping`, Chroma's bundled Python, Redis's own CLI) rather
than adding extra packages purely for monitoring.

### 2.5 Environment contract (`deployment/`)
Complete `.env.example` files (root + reconstructed backend/frontend) and
`ENV_VARIABLES.md`, including a cross-reference table for the two
separate namespaces this stack has (`MARIADB_*`/`MINIO_*` container
bootstrap vars vs. `DB_*`/`AWS_*` Laravel application vars) that must be
kept in sync by hand.

## 3. Key architecture decisions

| Decision | Rationale |
|---|---|
| Single public entrypoint (`frontend`) | Browser only ever talks to one origin; no CORS config needed; mirrors the existing vite dev-proxy behavior so dev/prod request paths match |
| No source bind-mounts in the base file | Makes the base file deployable as-is on a server; dev hot-reload moved to the auto-loaded override |
| 3-way queue split | CPU-bound and I/O-bound workloads scale independently instead of competing for the same worker pool |
| Pinned image tags everywhere | Build reproducibility; `:latest` on `chromadb/chroma`/`minio/minio`/`minio/mc` was a real risk already realized once (see §4) |
| `.env` mandatory in every mode, no exceptions | The alternative (per-file fallback defaults) was tried, found to be structurally inert against Compose's per-file interpolation, and removed — see `docs/ACCEPTANCE_REPORT.md` Defect 1 |

## 4. Honest statement of current limitations

- **Backend image is not yet fully immutable.** `backend/` has no
  committed `composer.json`/`artisan` yet (see target repo `README.md`).
  The Dockerfile detects this and falls back to the existing
  `docker-entrypoint.sh` runtime bootstrap, exactly as Phase 0/1 did. It
  will automatically become immutable on the next build once the skeleton
  is committed — no further Dockerfile changes will be needed.
- **MinIO's official Docker image carries a disclosed, unpatched CVE**
  and the vendor discontinued Docker Hub publishing for the community
  edition. Mitigated (internal-only network, no STS features), not fixed.
  Escalated to PM — see `docs/DEPLOYMENT.md` §6.
- **Not runtime-validated.** See `docs/VALIDATION_REPORT.md` for exactly
  what was and wasn't checked, and why.
