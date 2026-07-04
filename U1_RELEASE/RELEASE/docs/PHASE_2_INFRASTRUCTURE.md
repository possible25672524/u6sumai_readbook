# PHASE_2_INFRASTRUCTURE.md

Team: U1 (DevOps Lead) · Phase: 2a (Infrastructure) · Version: v4.1
(This file did not exist in the v4 delivery — its absence was finding #1 of
the v4 acceptance review. It exists now and is the canonical Phase 2
infrastructure reference. `DEPLOYMENT.md` remains the operational runbook;
this file is the architecture + decision + defect-closure record.)

---

## 1. Scope

Infrastructure only: Docker architecture, compose files, Dockerfiles,
health checks, environment variable contracts, deployment documentation.
**Not in scope:** Phase 2b application code (OCR/Whisper/Embedding
services, upload controllers, queue Job classes, ChromaDB client
integration) — tracked separately in `project_memory.md` §10.

## 2. Architecture summary

```
Internet ──▶ frontend (nginx, SPA, ONLY public port)
                 │ proxies /api/* internally
                 ▼
            nginx (API gateway, internal-only) ──fastcgi──▶ backend (php-fpm)
                                                                  │
                                        ┌─────────────────────────┼───────────────────────┐
                                        ▼                         ▼                       ▼
                              queue-worker-ocr            queue-worker-ai        queue-worker-default
                              (Tesseract/ffmpeg,           (Whisper/OpenAI/       (catch-all)
                               CPU-bound)                   Claude, I/O-bound)

   MariaDB · Redis · ChromaDB · MinIO — internal network only, no host ports in the base file
```

Full rationale for each decision (single public entrypoint, 3-way queue
split, no source bind-mounts in the base file, image pinning) is recorded
in `project_memory.md` §9.4 and is not repeated here.

## 3. Component inventory

| Component | File(s) | Status |
|---|---|---|
| Compose orchestration | `docker-compose.yml`, `.override.yml`, `.prod.yml` | Fixed (§6) |
| Backend image | `backend/Dockerfile` + `backend/docker/php/*`, `backend/docker/php-fpm/*` | Fixed (§6) |
| Frontend image | `frontend/Dockerfile` + `frontend/docker/nginx/default.conf` | No defects found |
| API gateway config | `docker/nginx/backend.conf` | No defects found |
| Env contracts | `.env.example`, `backend/.env.example`, `frontend/.env.example`, `ENV_VARIABLES.md` | Fixed (§6) |
| Dev bootstrap | `scripts/bootstrap-env.sh` | New (§6) |
| Runbook | `DEPLOYMENT.md` | No defects found |
| This file | `PHASE_2_INFRASTRUCTURE.md` | New (§6) |

## 4. Required infrastructure (Docker Compose v2.20+)

- Docker Engine with Compose V2 plugin (`docker compose`, not legacy
  `docker-compose`).
- Outbound access to: Docker Hub (or your mirror), Packagist, npm
  registry, Anthropic API, OpenAI API.
- A `.env`, `backend/.env`, `frontend/.env` in every environment — see
  `ENV_VARIABLES.md`. There is no working "no `.env` needed" mode, by
  design (§6, Defect 1).

## 5. Health check reference

(unchanged from `DEPLOYMENT.md` §3 — see that file for the full table.)

## 6. Defect closure log (v4 → v4.1)

This section is the audit trail requested by the v4 acceptance review.
Each defect found in that review is closed here.

### Defect 1 — `docker-compose.override.yml` dev-credential fallback was inert

- **Root Cause:** The base file declares `MARIADB_PASSWORD`,
  `MARIADB_ROOT_PASSWORD`, `MINIO_ROOT_USER`, `MINIO_ROOT_PASSWORD` as
  mandatory (`${VAR:?msg}`). Docker Compose interpolates `${...}`
  expressions independently per file at load time. The override file's
  `${VAR:-default}` expressions for the *same* variables could never
  "rescue" the base file's mandatory check, because the base file's
  expression fails first, regardless of what the override would have
  provided for the merged result. The override's fallback values were
  therefore dead code that implied a safety net which didn't exist.
- **Fix Applied:** Removed the misleading fallback `environment:` blocks
  from `docker-compose.override.yml` for `mariadb`/`minio`. Added a clear
  comment explaining why, and added `scripts/bootstrap-env.sh`, which
  makes "copy every `.env.example` to `.env` if missing" a single
  one-time command for **every** environment (dev included), removing the
  need for any in-compose fallback at all.
- **Files Created:** `scripts/bootstrap-env.sh`
- **Files Modified:** `docker-compose.override.yml`, `docker-compose.yml`
  (added a top-of-file note cross-referencing this fix)
- **Validation Performed:** Re-parsed all three compose files with
  `yaml.safe_load` (pure syntax check — no Docker daemon available in this
  sandbox to actually run `docker compose config`); manually traced the
  variable-resolution order described above against Docker Compose's
  documented per-file interpolation behavior. **Not validated against a
  live `docker compose up` run** — recommend CI does that before merge.

### Defect 2 — `deploy.resources.limits` not guaranteed outside Swarm on older Compose

- **Root Cause:** `deploy.resources.limits` is fully honored by `docker
  compose up` (non-Swarm) only on Compose v2.20+; older v2 releases parse
  but silently ignore it. The original delivery specified no minimum
  version and had no fallback, so the stated CPU/memory caps on
  `queue-worker-ocr`/`queue-worker-ai` were not actually guaranteed on
  every installation that would run this file.
- **Fix Applied:** Added legacy top-level `mem_limit`/`cpus` keys
  alongside the existing `deploy.resources.limits` block on both services
  — these are honored by every Compose V2 release with no version
  caveat, so the cap now applies unconditionally. Added an explicit
  "Requires Docker Compose v2.20+" note (for the modern path) at the top
  of `docker-compose.yml`.
- **Files Created:** None
- **Files Modified:** `docker-compose.yml` (`queue-worker-ocr`,
  `queue-worker-ai`, plus the top-of-file header comment)
- **Validation Performed:** YAML syntax re-validated. Cross-checked that
  `mem_limit`/`cpus` and `deploy.resources.limits` are independent keys
  that can coexist without schema conflict. **Not validated by actually
  starting a container and inspecting its real cgroup limits** — that
  requires a Docker daemon, unavailable in this sandbox; recommend `docker
  inspect <container> | grep -i memory` as a CI/staging smoke test.

### Defect 3 — `backend/Dockerfile` claimed immutability it doesn't yet have

- **Root Cause:** `backend/` has not yet had a Laravel skeleton
  (`composer.json`/`composer.lock`/`artisan`) committed to it (see
  `README.md`: "ยังไม่มี Laravel skeleton เต็มรูปแบบ"). The v4 Dockerfile did
  `COPY . .` and documented the image as immutable, but never ran
  `composer install` at build time and could not have — there is no
  `composer.json` in the build context yet to install against. The image
  therefore still silently depended on `docker-entrypoint.sh`'s first-boot
  `composer create-project` bootstrap, contradicting the documented
  "no runtime dependency on the host/network" claim.
- **Fix Applied:** Added a conditional `RUN` step: if `composer.json` is
  present at build time, run `composer install --no-dev --optimize-
  autoloader` (true immutability); if not, print an explicit, honest log
  message explaining that vendor/ will be installed at first container
  start instead, and why. This makes the image **self-upgrading**: once
  the Laravel skeleton is committed and the image is rebuilt, it
  automatically becomes truly immutable with no further Dockerfile edits.
  `docker-entrypoint.sh` was reviewed (not modified) and already guards
  both states correctly (`if [ ! -f artisan ]` / `if [ ! -d vendor ]`), so
  no changes were needed there.
- **Files Created:** None
- **Files Modified:** `backend/Dockerfile`
- **Validation Performed:** Static structural check (presence of the
  conditional block, balanced `if`/`fi`, `FROM`/`ENTRYPOINT` intact) via a
  small Python script. **Full `docker build` was not executed**: this
  sandbox's network egress allow-list does not include the Debian package
  mirrors needed by `php:8.3-fpm-bookworm`'s `apt-get install` step, so the
  image cannot be built here. This is a real limitation of this review,
  not a claim that the build succeeds — recommend the first CI build of
  this Dockerfile be treated as the actual validation gate, on both states
  (with and without a committed `composer.json`) if feasible.

### Defect 4 — `.env.example` deliverable was append-only, not complete

- **Root Cause:** U1 never had read access to the real
  `backend/.env.example` / `frontend/.env.example` files, only to
  `API_AUTH.md`/`README.md`/`project_memory.md`/`docker-compose.yml`. The
  v4 delivery produced "append this" documents instead of complete files,
  which doesn't satisfy "generate a complete `.env.example`
  specification."
- **Fix Applied:** Reconstructed complete `backend/.env.example` and
  `frontend/.env.example` from every Phase-1 requirement documented in the
  four source files above (DB/Redis/Mail/Sanctum/Admin-seed values for
  backend; the one confirmed `VITE_API_PROXY_TARGET` reference for
  frontend), clearly marked `[PHASE 1 - RECONSTRUCTED]` vs.
  `[PHASE 2 - NEW]`. Created `ENV_VARIABLES.md` as the full specification:
  every variable, its required/optional status, its default, and —
  critically — a cross-reference table showing which root `.env`
  bootstrap variables (`MARIADB_*`, `MINIO_*`) must match which
  `backend/.env` application variables (`DB_*`, `AWS_*`), since those live
  in different namespaces and silently drifting apart is a common failure
  mode in this kind of stack. Added explicit, numbered merge instructions
  for whoever holds the real files. Deleted the superseded
  `backend.env.example.additions.md` / `frontend.env.example.additions.md`
  files from the v4 delivery.
- **Files Created:** `backend/.env.example` (full), `frontend/.env.example`
  (full), `ENV_VARIABLES.md`
- **Files Modified:** None (additions-only files were deleted, not edited)
- **Validation Performed:** Cross-checked every reconstructed variable
  against its source mention in `API_AUTH.md`/`README.md`/
  `project_memory.md`/`docker-compose.yml` (traceable — see the
  cross-reference table in `ENV_VARIABLES.md` §2). **Not validated against
  the real, currently-checked-in files**, because U1 does not have them —
  this is explicitly called out as a maintainer merge step, not silently
  assumed correct.

## 7. Escalated decisions (PM, not blockers to infra completion)

- **MinIO image risk** (unpatched CVE, discontinued Docker Hub
  publishing — full detail in `DEPLOYMENT.md` §6 / `project_memory.md`
  §11). This is a storage-architecture decision for the team/PM, not a
  defect in this infrastructure work, and is explicitly **not** treated as
  a blocker to closing the four findings above.
- Whether the reconstructed `backend/.env.example`/`frontend/.env.example`
  should fully replace the real files or be hand-merged (recommendation:
  hand-merge, see §6 Defect 4 / `ENV_VARIABLES.md` §4).

## 8. Changelog

- **v4.1** (this document): closed Defects 1–4 from the v4 acceptance
  review; created this file.
- **v4**: initial Phase 2 infrastructure delivery (compose files,
  Dockerfiles, health checks, deployment docs, `project_memory.md` update).
