# U1 Infrastructure Release — AI Study Assistant Platform

**Team:** U1 (DevOps Lead) · **Scope:** Phase 2a Infrastructure only ·
**Version:** v4.1 (infra) / Release packaging v4.2 · **Status:** Accepted

This package is a **release-ready repackaging** of already-accepted U1
infrastructure work. Nothing inside it is new functionality — it is the
same compose files, Dockerfiles, configs, scripts, and docs that already
passed acceptance review, reorganized for handoff/integration. See
`docs/ACCEPTANCE_REPORT.md` for the full acceptance history.

## What this is NOT

This package does **not** contain Phase 2b application code (OCR/Whisper/
Embedding services, controllers, queue Jobs). See `project_memory.md` §10
in the target repository for that boundary.

## Directory structure (this package) → integration target (your repo)

| In this package | Goes to (repo root) | Contents |
|---|---|---|
| `infrastructure/docker-compose.yml` | `docker-compose.yml` | Production-safe base |
| `infrastructure/docker-compose.override.yml` | `docker-compose.override.yml` | Dev convenience (auto-loaded) |
| `infrastructure/docker-compose.prod.yml` | `docker-compose.prod.yml` | Optional prod overlay |
| `docker/backend/Dockerfile` | `backend/Dockerfile` | PHP-FPM + Tesseract/ffmpeg/poppler |
| `docker/backend/php/custom.ini` | `backend/docker/php/custom.ini` | Upload limits, opcache |
| `docker/backend/php-fpm/www.conf` | `backend/docker/php-fpm/www.conf` | FPM ping/status for healthchecks |
| `docker/backend/php-fpm/healthcheck.sh` | `backend/docker/php-fpm/healthcheck.sh` | Backend container healthcheck |
| `docker/frontend/Dockerfile` | `frontend/Dockerfile` | Multi-stage dev/builder/production |
| `docker/frontend/nginx/default.conf` | `frontend/docker/nginx/default.conf` | SPA + `/api` proxy (baked into image) |
| `docker/nginx/backend.conf` | `docker/nginx/backend.conf` | API gateway config |
| `docker/nginx/frontend.conf` | `docker/nginx/frontend.conf` | Reference copy of the above SPA config |
| `deployment/root/.env.example` | `.env.example` | Infra/container credentials |
| `deployment/backend/.env.example` | `backend/.env.example` | **Reconstructed — merge, don't overwrite. See ENV_VARIABLES.md.** |
| `deployment/frontend/.env.example` | `frontend/.env.example` | **Reconstructed — merge, don't overwrite.** |
| `deployment/ENV_VARIABLES.md` | repo root (or `docs/`) | Full env var spec + merge instructions |
| `scripts/bootstrap-env.sh` | `scripts/bootstrap-env.sh` | One-time `.env` setup helper |
| `docs/*.md` | repo root (or `docs/`) | Reference + audit-trail documentation |

## Quickstart (once integrated)

```bash
./scripts/bootstrap-env.sh        # copies all .env.example -> .env if missing
# edit .env, backend/.env, frontend/.env — see deployment/ENV_VARIABLES.md
docker compose up -d --build      # base + override (dev) auto-merge
docker compose exec backend php artisan migrate
```

For a production deploy (no dev bind-mounts, no exposed DB/cache ports):
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Document index

| File | Purpose |
|---|---|
| `README.md` (this file) | Orientation + integration mapping |
| `CHANGELOG.md` | Version history of this infrastructure work |
| `RELEASE_MANIFEST.md` | Structured inventory: files, components, env vars, services, health checks, limitations |
| `docs/IMPLEMENTATION_REPORT.md` | What was built and why |
| `docs/VALIDATION_REPORT.md` | What was checked, how, and what was NOT (sandbox limitations) |
| `docs/ACCEPTANCE_REPORT.md` | Full review history: v4 findings → v4.1 fixes → final acceptance |
| `docs/DEPLOYMENT.md` | Operational runbook |
| `docs/PHASE_2_INFRASTRUCTURE.md` | Architecture reference + defect-closure log |
| `deployment/ENV_VARIABLES.md` | Environment variable specification + merge instructions |

## Known limitations (see `RELEASE_MANIFEST.md` for the full list)

- Validated statically only (YAML parse, Dockerfile structure) — no Docker
  daemon or Debian-mirror network access was available in the environment
  that built this package. First real `docker compose build && up` should
  be treated as a required CI gate, not assumed to already be proven.
- `backend/.env.example` / `frontend/.env.example` are reconstructed, not
  copies of the real checked-in files (U1 never had read access to them).
  Merge, don't overwrite.
- MinIO's Docker image carries an unpatched, disclosed CVE and the vendor
  has discontinued Docker Hub publishing for the community edition — this
  is escalated to PM, not fixed in this package. See
  `docs/DEPLOYMENT.md` §6.
