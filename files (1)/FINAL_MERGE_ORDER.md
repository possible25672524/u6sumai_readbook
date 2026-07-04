# FINAL MERGE ORDER
**Project:** AI Study Assistant Platform  
**Release:** Phase 2  
**Integration Lead:** U5  
**Date:** 2026-07-01  
**Pre-condition:** All PATCH-01 through PATCH-07 must be staged before any merge begins.

---

## MERGE SEQUENCE

The following order eliminates merge conflicts by resolving infrastructure first, then the AI layer, then the application layer, then the frontend, then applying integration patches last.

```
Step 1 ── U1_RELEASE  (Infrastructure base)
Step 2 ── U2_RELEASE  (AI Layer — depends on U1 Docker environment)
Step 3 ── U3_RELEASE  (Backend — depends on U1 infra + U2 AI services)
Step 4 ── U4_RELEASE  (Frontend — depends on U3 API contract)
Step 5 ── U5 Patches  (Integration fixes — applied on top of merged U1–U4)
```

---

## STEP-BY-STEP MERGE INSTRUCTIONS

### Step 1 — Merge U1_RELEASE
**Branch:** `u1/phase-2-release` → `integration/phase-2`  
**Files:** infrastructure/, docker/, scripts/, deployment/  
**Prerequisites:** None — U1 is the base layer  
**Expected conflicts:** None  
**Validation after merge:**
```bash
docker compose config --quiet   # validates docker-compose.yml syntax
ls infrastructure/docker-compose*.yml   # confirm all 3 variants present
```

---

### Step 2 — Merge U2_RELEASE
**Branch:** `u2/phase-2-release` → `integration/phase-2`  
**Files:** backend/app/Contracts/AI/, backend/app/Services/AI/, backend/app/Providers/AIServiceProvider.php, backend/app/Services/{Summarization,QuestionGeneration,RAGChat,Embedding,Transcription}Service.php, backend/app/Models/AIUsageLog.php, backend/database/migrations/2026_06_23_230621_*, backend/config/ai.php, backend/tests/Unit/AI/  
**Prerequisites:** Step 1 complete  
**Expected conflicts:** None — U2 files have unique paths  
**Validation after merge:**
```bash
grep -r "AIServiceProvider" backend/bootstrap/app.php   # will be absent — PATCH-01 adds it
ls backend/app/Services/AI/Providers/   # 4 provider files present
ls backend/app/Services/EmbeddingService.php   # U2 version present (will be patched)
```

---

### Step 3 — Merge U3_RELEASE
**Branch:** `u3/phase-2-release` → `integration/phase-2`  
**Files:** backend/app/Http/, backend/app/Models/, backend/app/Services/{Document,Ocr,Chroma,TextChunker,Transcription,Embedding}*, backend/app/Jobs/, backend/app/Policies/, backend/app/Events/, backend/app/Listeners/, backend/app/Notifications/, backend/app/Providers/{App,Event}ServiceProvider.php, backend/bootstrap/app.php, backend/routes/api.php, backend/config/{services,filesystems,queue}.php, backend/database/migrations/2026_06_23_00001*, backend/database/factories/, backend/tests/  
**Prerequisites:** Steps 1–2 complete  

**⚠️ EXPECTED CONFLICTS — resolve as follows:**

| File | Conflict | Resolution |
|------|----------|------------|
| `backend/app/Services/EmbeddingService.php` | U2 version vs U3 version | **Keep U2 version** — PATCH-03 will add embedChunks() adapter |
| `backend/app/Services/TranscriptionService.php` | U2 version vs U3 version | **Keep U2 version** — U3 version uses direct HTTP; U2 AIManager-based is canonical |

**Conflict resolution commands:**
```bash
# For both conflicted files — keep U2 (already-merged) version
git checkout --ours backend/app/Services/EmbeddingService.php
git checkout --ours backend/app/Services/TranscriptionService.php
git add backend/app/Services/EmbeddingService.php
git add backend/app/Services/TranscriptionService.php
```

**Validation after merge:**
```bash
php artisan route:list --path=api   # all 26 Phase 2 routes present
ls backend/app/Jobs/   # 4 job files present
ls backend/database/migrations/ | wc -l   # 9 Phase 2 migration files
```

---

### Step 4 — Merge U4_RELEASE
**Branch:** `u4/phase-2-release` → `integration/phase-2`  
**Files:** frontend/  
**Prerequisites:** Steps 1–3 complete  
**Expected conflicts:** None — frontend/ path is isolated  
**Validation after merge:**
```bash
cd frontend && npm install && npm run build   # must succeed with exit 0
ls frontend/dist/   # built assets present
```

---

### Step 5 — Apply U5 Integration Patches
**Source:** `u5/integration-patches`  
**Prerequisites:** Steps 1–4 complete — patches must be applied to the fully merged state  
**Apply in order:**

```bash
# PATCH-01: Add AIServiceProvider to bootstrap/app.php
# Manually edit backend/bootstrap/app.php — add to withProviders():
#   \App\Providers\AIServiceProvider::class,

# PATCH-02: Update ChromaDbService to use /api/v2/
cp patches/PATCH-02-chromadb-api-v2.php backend/app/Services/ChromaDbService.php

# PATCH-03: Merge EmbeddingService — add embedChunks() to U2 base
cp patches/PATCH-03-embedding-service-merge.php backend/app/Services/EmbeddingService.php

# PATCH-04: Fix TranscribeAudioJob DTO access
cp patches/PATCH-04-transcribe-job-dto.php backend/app/Jobs/TranscribeAudioJob.php

# PATCH-05: Fix queue-worker-ai to include 'embed' queue
# Edit infrastructure/docker-compose.yml queue-worker-ai command:
#   --queue=embed,embedding,ai-generation

# PATCH-06: Align config/services.php
cp patches/PATCH-06-openai-config-key.php backend/config/services.php

# PATCH-07: Fix ProtectedRoute and MainLayout role comparison
# Apply changes from PATCH-07-protectedroute-role.md to:
#   frontend/src/app/ProtectedRoute.jsx  (roles.includes → role?.slug)
#   frontend/src/app/layouts/MainLayout.jsx  (role === 'admin' → role?.slug === 'admin')

git add -A
git commit -m "chore(integration): apply U5 PATCH-01 through PATCH-07 integration fixes"
```

**Validation after patches:**
```bash
# Verify all patches applied
grep "AIServiceProvider" backend/bootstrap/app.php        # must be present
grep "api/v2" backend/app/Services/ChromaDbService.php   # must be present
grep "embedChunks" backend/app/Services/EmbeddingService.php  # must be present
grep "result->text" backend/app/Jobs/TranscribeAudioJob.php   # must be present (not result['text'])
grep "embed,embedding" infrastructure/docker-compose.yml      # must be present
grep "api_key" backend/config/services.php                    # must be present
grep "role?.slug" frontend/src/app/ProtectedRoute.jsx         # must be present
```

---

## POST-MERGE CONFLICT RESOLUTION STRATEGY

| Scenario | Strategy |
|----------|----------|
| EmbeddingService.php conflict | Keep U2 version (`--ours` after U2 merge). Apply PATCH-03 after all merges. |
| TranscriptionService.php conflict | Keep U2 version (`--ours` after U2 merge). |
| bootstrap/app.php conflict | Keep U3 version as base, then add AIServiceProvider manually (PATCH-01). |
| config/services.php conflict | Keep U3 version as base, then apply PATCH-06. |
| Any other conflict | Favour the team that owns the file per FINAL_RELEASE_REPORT.md §2 ownership table. |

---

## POST-MERGE VALIDATION CHECKLIST

```
STRUCTURE
[ ] ls backend/app/Services/EmbeddingService.php      → U2+PATCH-03 version
[ ] ls backend/app/Services/TranscriptionService.php  → U2 canonical version
[ ] grep "AIServiceProvider" backend/bootstrap/app.php → present
[ ] grep "api/v2" backend/app/Services/ChromaDbService.php → no /api/v1/ references
[ ] grep "embed,embedding" infrastructure/docker-compose.yml → present

BUILD
[ ] docker compose build --no-cache       → exit 0
[ ] cd frontend && npm run build          → exit 0, dist/ created

RUNTIME
[ ] docker compose up -d                  → all services (healthy)
[ ] curl http://localhost:8000/up         → HTTP 200
[ ] php artisan migrate                   → exit 0, no errors
[ ] php artisan route:list | grep api     → 26+ routes
[ ] php artisan tinker → AIManager healthCheck() → all providers OK

END-TO-END
[ ] POST /api/auth/login                  → token returned
[ ] POST /api/documents (PDF upload)      → 201, document created
[ ] GET  /api/documents/{id}/status       → processing pipeline running
[ ] Wait for pipeline completion          → status = 'completed'
[ ] Admin user accesses /admin pages      → accessible (PATCH-07 verified)
```

