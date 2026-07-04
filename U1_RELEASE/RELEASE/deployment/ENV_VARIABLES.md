# ENV_VARIABLES.md — Environment Variable Specification (Phase 2)

This closes the "`.env.example` deliverable gap" from the Phase 2 acceptance
review. It covers all three `.env.example` files (root, `backend/`,
`frontend/`) plus the cross-references between them that aren't obvious
from any single file.

## 1. Why this document exists

U1 (DevOps) does not have read access to the real, currently-checked-in
`backend/.env.example` / `frontend/.env.example` — only to `API_AUTH.md`,
`README.md`, `project_memory.md`, and `docker-compose.yml`. The files at
`backend/.env.example` and `frontend/.env.example` in this delivery are
**reconstructed** from what those four documents say the application
needs, plus this round's new Phase 2 variables. They are a strong starting
point, not a guaranteed byte-for-byte match of whatever a Backend/Frontend
engineer already committed.

## 2. Required environment variables — full table

### Root `.env` (consumed by `docker-compose*.yml`)

| Variable | Required | Default | Purpose |
|---|---|---|---|
| `FRONTEND_PORT` | No | `8080` (prod) / `5173` (dev override) | Host port for the public SPA |
| `NGINX_PORT` | No (dev only) | `8000` | Host port for direct API access in dev |
| `MARIADB_DATABASE` | No | `study_assistant` | DB name |
| `MARIADB_USER` | No | `study_assistant` | DB app user |
| `MARIADB_PASSWORD` | **Yes** | none | DB app user password — must equal backend's `DB_PASSWORD` |
| `MARIADB_ROOT_PASSWORD` | **Yes** | none | DB root password |
| `MARIADB_PORT` | No (dev only) | `3306` | |
| `REDIS_PORT` | No (dev only) | `6379` | |
| `CHROMA_VERSION` | No | `1.5.7` | Image tag pin |
| `CHROMA_PORT` | No (dev only) | `8001` | |
| `MINIO_VERSION` / `MINIO_MC_VERSION` | No | see `docker-compose.yml` | Image tag pins — see MinIO risk note |
| `MINIO_ROOT_USER` | **Yes** | none | Must equal backend's `AWS_ACCESS_KEY_ID` |
| `MINIO_ROOT_PASSWORD` | **Yes** | none | Must equal backend's `AWS_SECRET_ACCESS_KEY` |
| `MINIO_API_PORT` / `MINIO_CONSOLE_PORT` | No (dev only) | `9000` / `9001` | |
| `MINIO_BUCKET_RAW` | No | `study-assistant-raw` | Must equal backend's `AWS_BUCKET` |
| `MINIO_BUCKET_PROCESSED` | No | `study-assistant-processed` | Must equal backend's `AWS_BUCKET_PROCESSED` |

`**Yes**` = no default exists anywhere; `docker compose up` will refuse to
start without it (see `docker-compose.yml`'s `${VAR:?...}` syntax). This is
deliberate — see §3 below.

### `backend/.env`

All standard Laravel variables apply (`APP_KEY`, `APP_ENV`, etc. — generate
`APP_KEY` via `php artisan key:generate`, never hand-write it). Phase-2
specific / cross-referenced variables:

| Variable | Required | Cross-references |
|---|---|---|
| `DB_HOST` | Yes | must be `mariadb` (Docker service name, not `localhost`) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Yes | must equal root `.env`'s `MARIADB_DATABASE` / `MARIADB_USER` / `MARIADB_PASSWORD` |
| `REDIS_HOST` | Yes | must be `redis` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | Yes | must equal root `.env`'s `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` |
| `AWS_BUCKET` / `AWS_BUCKET_PROCESSED` | Yes | must equal root `.env`'s `MINIO_BUCKET_RAW` / `MINIO_BUCKET_PROCESSED` |
| `AWS_ENDPOINT` | Yes | must be `http://minio:9000` |
| `CHROMA_HOST` / `CHROMA_PORT` | Yes | must be `chromadb` / `8000` (container-internal port, not the host-mapped `CHROMA_PORT` from root `.env`) |
| `ANTHROPIC_API_KEY` / `ANTHROPIC_MODEL` | Yes | real Anthropic key; model defaults to `claude-sonnet-4-6` |
| `OPENAI_API_KEY` | Yes | used for both embeddings and Whisper |
| `OCR_LANGUAGES` | No | defaults to `eng+tha`; must be a subset of what's installed in `backend/Dockerfile` (`tesseract-ocr-eng`, `tesseract-ocr-tha`) |

### `frontend/.env`

| Variable | Required | Purpose |
|---|---|---|
| `VITE_API_PROXY_TARGET` | No (dev only) | vite dev-server proxy target, unused in prod build |
| `VITE_API_BASE_URL` | No | defaults to `/api`, same-origin in both dev and prod |
| `VITE_MAX_UPLOAD_MB` | No | UI-only display value; must stay ≤ the real PHP/nginx limits (500MB) |

## 3. Why secrets have no defaults anywhere (by design)

Earlier drafts of `docker-compose.override.yml` tried to give
`MARIADB_PASSWORD`/`MINIO_ROOT_USER`/etc. dev-only fallback values. That
was removed (see `PHASE_2_INFRASTRUCTURE.md` §6 "Defect 1") because it
didn't actually work and implied a false safety net. The real, only
required step in **every** environment is:

```bash
./scripts/bootstrap-env.sh   # copies all three .env.example -> .env if missing
```

Then edit the three resulting `.env` files before running
`docker compose up`.

## 4. Merge instructions for repository maintainers

You almost certainly already have real `backend/.env.example` and
`frontend/.env.example` files with Phase 1 content U1 couldn't see. Do
**not** blindly overwrite them with the files in this delivery. Instead:

1. `diff` the real file against the reconstructed one in this delivery:
   ```bash
   diff backend/.env.example   <delivery>/backend/.env.example
   diff frontend/.env.example  <delivery>/frontend/.env.example
   ```
2. Keep every line that exists only in your real file (U1 had no
   visibility into those — they are not wrong, just unknown to this round).
3. Add every line under this delivery's `[PHASE 2 - NEW]` markers that
   doesn't already exist in your real file.
4. For any line that exists in **both** with different values (most likely
   `APP_URL`, `FRONTEND_URL`, `DB_*`), keep your real file's value — it
   reflects actual Phase 1 decisions U1 didn't have access to.
5. Re-run the cross-reference table in §2 above and confirm
   `DB_*`/`AWS_*`/`CHROMA_*` actually match the corresponding root `.env`
   values — this is the most common source of "works on my machine, fails
   in the container" bugs in this kind of stack.
6. Re-generate `APP_KEY` (`php artisan key:generate`) if the merged file
   doesn't already have one — never copy an `APP_KEY` between
   environments.
7. Delete this file's "RECONSTRUCTED" banner comments once merged.
