# CHANGELOG — U1 Infrastructure (Phase 2a)

All notable changes to U1's infrastructure deliverable. Versions track
`project_memory.md`'s decision log, not semantic versioning of the
application itself (the application has no version yet — Phase 2b hasn't
started).

## v4.2 — 2026-06-28 — Release packaging

- No functional changes. Repackaged the already-accepted v4.1 deliverable
  into a structured `RELEASE/` directory (`infrastructure/`, `docker/`,
  `deployment/`, `scripts/`, `docs/`) for integration handoff.
- Added: `README.md`, `CHANGELOG.md`, `RELEASE_MANIFEST.md`,
  `docs/IMPLEMENTATION_REPORT.md`, `docs/VALIDATION_REPORT.md`,
  `docs/ACCEPTANCE_REPORT.md` (this set of six documents).
- Updated `project_memory.md` with the release-packaging entry.

## v4.1 — 2026-06-23 — Acceptance-review defect closure

- **Added:** `PHASE_2_INFRASTRUCTURE.md` (was missing in v4).
- **Added:** `scripts/bootstrap-env.sh`.
- **Added:** complete `backend/.env.example`, `frontend/.env.example`
  (reconstructed), `ENV_VARIABLES.md`.
- **Fixed:** `docker-compose.override.yml` — removed dev-credential
  fallbacks for MariaDB/MinIO that could never actually apply, given the
  base file's mandatory (`:?`) requirement on the same variables.
- **Fixed:** `docker-compose.yml` — added legacy `mem_limit`/`cpus`
  alongside `deploy.resources.limits` on `queue-worker-ocr`/
  `queue-worker-ai` so the resource cap is enforced regardless of Compose
  version.
- **Fixed:** `backend/Dockerfile` — replaced an overstated "this image is
  immutable" claim with a conditional, self-upgrading build step that's
  honest about current behavior (no committed Laravel skeleton yet means
  no build-time `composer install` yet).
- **Removed:** `backend.env.example.additions.md`,
  `frontend.env.example.additions.md` (superseded by the complete files
  above).

## v4 — 2026-06-23 — Initial Phase 2 infrastructure delivery

- **Added:** `docker-compose.yml` (rewritten as production-safe base),
  `docker-compose.override.yml` (dev convenience, auto-loaded),
  `docker-compose.prod.yml` (optional prod overlay).
- **Added:** `backend/Dockerfile` (`php:8.3-fpm-bookworm` + Tesseract
  `eng+tha` + ffmpeg + poppler-utils + ghostscript), plus
  `backend/docker/php/custom.ini`, `backend/docker/php-fpm/www.conf`,
  `backend/docker/php-fpm/healthcheck.sh`.
- **Added:** `frontend/Dockerfile` (multi-stage: development / builder /
  production), `frontend/docker/nginx/default.conf`.
- **Added:** `docker/nginx/backend.conf` (API gateway, no codebase
  bind-mount required — pure fastcgi pass-through).
- **Added:** health checks on every service (backend, 3× queue-worker,
  nginx, frontend, mariadb, redis, chromadb, minio).
- **Added:** `DEPLOYMENT.md`.
- **Changed:** split the single Phase 1 generic queue-worker into three
  workload-specific containers (`ocr`, `ai`, `default`).
- **Changed:** ingress architecture — `frontend` becomes the sole public
  entrypoint, proxying `/api/*` internally to the `nginx` API gateway.
- **Pinned:** previously-`:latest` images (`chromadb/chroma`,
  `minio/minio`, `minio/mc`) to specific tags for build reproducibility.
- **Found (not fixed — escalated):** MinIO's last published Docker Hub
  image carries an unpatched, disclosed CVE (GHSA-jjjj-jwhf-8rgr); the
  community edition's Docker Hub publishing has been discontinued. Flagged
  in `project_memory.md` §11 for a PM/team decision.
