# VALIDATION_REPORT.md — U1 Infrastructure (Phase 2a)

This report exists to draw a hard line between "checked" and "assumed."
Nothing here is hidden or softened — the goal is that whoever runs this
in CI knows exactly what's already been verified and what they're the
first to actually prove.

## 1. What was validated, and how

| Artifact | Method | Result |
|---|---|---|
| `docker-compose.yml`, `.override.yml`, `.prod.yml` | `python3 -c "import yaml; yaml.safe_load(open(f))"` re-run after every edit | All three parse as valid YAML |
| `docker-compose.yml` variable-resolution logic (the Defect 1 fix) | Manual trace against Docker Compose's documented per-file `${...}` interpolation order | Confirmed the prior fallback was dead code; confirmed the fix (removing it + `bootstrap-env.sh`) is logically sound |
| `backend/Dockerfile` structure | Static Python check: presence of the conditional `composer install` block, balanced `if`/`fi`, intact `FROM`/`ENTRYPOINT` | Passes structural check |
| Reconstructed `backend/.env.example` / `frontend/.env.example` variables | Traced every variable back to its source mention in `API_AUTH.md` / `README.md` / `project_memory.md` / `docker-compose.yml` | Every variable is traceable; documented in `deployment/ENV_VARIABLES.md` §2 |
| Health check command syntax | Manual review against each tool's documented CLI (`redis-cli ping`, `cgi-fcgi` FastCGI ping convention, MariaDB's bundled `healthcheck.sh`) | Internally consistent; queue-worker healthchecks correctly use `pgrep` rather than the FPM-specific script (they don't run php-fpm) |
| Final acceptance review (prior turn) | Independent re-grep of every claimed fix against the actual files (not against prior self-reports) | All 6 checklist items re-confirmed |

## 2. What was explicitly NOT validated, and why

| Gap | Reason | Risk if skipped |
|---|---|---|
| **No `docker build` was ever run** for `backend/Dockerfile` or `frontend/Dockerfile` | This sandbox's network egress allow-list does not include Debian package mirrors (`backend/Dockerfile` is `php:8.3-fpm-bookworm`-based and needs `apt-get install`); no Docker daemon is available in this environment at all | Possible package-name typos, version incompatibilities, or apt resolution issues that only a real build would surface |
| **No `docker compose up` was ever run** | Same reason — no daemon | Possible schema-level rejections that pure YAML parsing can't catch (e.g. interaction between `deploy.resources.limits` and legacy `mem_limit`/`cpus` on the same service has not been confirmed valid by an actual `docker compose config` run, only by manual schema review) |
| **No live healthcheck was ever exercised** | Same reason | The ChromaDB heartbeat path (`/api/v2/heartbeat`, pinned to image tag `1.5.7`) was inferred from public versioning documentation, not confirmed against a running container |
| **Reconstructed `backend/.env.example`/`frontend/.env.example` were never diffed against the real checked-in files** | U1 does not have read access to them | Reconstructed file may miss real Phase-1-only variables, or have different default values than what's actually deployed today — this is why `deployment/ENV_VARIABLES.md` §4 requires a manual maintainer merge, not a blind overwrite |

## 3. Required follow-up validation (before first real deployment)

These are not optional nice-to-haves — they are the actual proof this
infrastructure works, which nothing in this delivery can substitute for:

1. `docker compose -f infrastructure/docker-compose.yml config` — confirms
   the merged config is schema-valid (catches anything pure YAML parsing
   missed).
2. `docker compose build backend frontend` — first real proof the
   Dockerfiles work end-to-end.
3. `docker compose up -d` followed by `docker compose ps` until every
   service reports `(healthy)` — first real proof the healthchecks and
   `depends_on` ordering work as designed.
4. `docker inspect <queue-worker container> --format '{{.HostConfig.Memory}}'`
   — confirms the `mem_limit` fallback (Defect 2 fix) is actually applied.
5. A manual OCR test once Phase 2b exists: upload a Thai-language scanned
   document and confirm `tesseract-ocr-tha` actually produces usable
   output — this infrastructure installs the package but Phase 2a has no
   way to exercise it without Phase 2b's application code.

## 4. Verdict

Static/structural validation: **complete, to the limit of what's possible
without a Docker daemon.** Runtime validation: **not performed, and not
claimed to have been.** This is documented here precisely so that gap is
a visible, tracked item rather than a silent assumption inherited by
whoever deploys this next.
