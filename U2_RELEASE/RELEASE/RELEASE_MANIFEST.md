# Release Manifest — U2_RELEASE

---

## Release Header

| Field | Value |
|---|---|
| **Team** | U2 — AI Integration Lead |
| **Version** | 2.0.0 |
| **Release Date** | 2026-06-24 |
| **Phase** | Phase 2 — AI Provider Architecture |
| **Project** | AI Study Assistant Platform |
| **Base Framework** | Laravel 12 (PHP 8.2+) |
| **Acceptance Status** | ✅ PASSED |
| **Ready For Merge** | **YES** |

---

## AI Providers

| Provider Class | Interface | Model | Purpose |
|---|---|---|---|
| `ClaudeProvider` | `AIProviderInterface` | `claude-sonnet-4-5` | Primary text generation: summarisation, question generation, RAG chat |
| `OpenAIChatProvider` | `AIProviderInterface` | `gpt-4o-mini` (configurable) | Fallback text generation |
| `OpenAIEmbeddingProvider` | `EmbeddingProviderInterface` | `text-embedding-3-small` | Document and query vector embeddings (1536 dimensions) |
| `WhisperProvider` | `TranscriptionProviderInterface` | `whisper-1` | Audio/video to text transcription |

---

## AI Interfaces

| Interface | Methods | Implemented By |
|---|---|---|
| `AIProviderInterface` | `chat()`, `getProviderName()`, `getDefaultModel()`, `ping()` | `ClaudeProvider`, `OpenAIChatProvider` |
| `EmbeddingProviderInterface` | `embed()`, `embedBatch()`, `getEmbeddingModel()`, `getDimensions()` | `OpenAIEmbeddingProvider` |
| `TranscriptionProviderInterface` | `transcribe()`, `getTranscriptionModel()` | `WhisperProvider` |

---

## AI Services (High-Level)

| Service Class | Module | Purpose |
|---|---|---|
| `SummarizationService` | Module 5 | 7-format document summarisation |
| `QuestionGenerationService` | Module 7 | 5-type AI question generation (JSON output) |
| `RAGChatService` | Module 9/10 | Grounded RAG chatbot; document-only answers |
| `EmbeddingService` | Module 4 | Document chunking, batch embedding, query embedding |
| `TranscriptionService` | Module 4 | Audio/video transcription pipeline |

---

## Files Included

### Total: 43 files

---

### Release Root (6 files)

| File | Type | Description |
|---|---|---|
| `README.md` | Documentation | Package overview and quick-start guide |
| `CHANGELOG.md` | Documentation | Full change history for Phases 0–2 |
| `IMPLEMENTATION_REPORT.md` | Documentation | Technical implementation detail |
| `VALIDATION_REPORT.md` | Documentation | Test coverage and validation results |
| `ACCEPTANCE_REPORT.md` | Documentation | Independent acceptance audit report |
| `RELEASE_MANIFEST.md` | Documentation | This file |
| `project_memory.md` | Documentation | Updated Single Source of Truth (Phases 0–2) |

---

### backend/app/Contracts/AI/ — Interfaces (3 files)

| File | Size | Checksum Basis |
|---|---|---|
| `AIProviderInterface.php` | ~40 lines | Namespace: `App\Contracts\AI` |
| `EmbeddingProviderInterface.php` | ~55 lines | Namespace: `App\Contracts\AI` |
| `TranscriptionProviderInterface.php` | ~45 lines | Namespace: `App\Contracts\AI` |

---

### backend/app/Models/ (1 file)

| File | Size | Namespace |
|---|---|---|
| `AIUsageLog.php` | ~100 lines | `App\Models` |

---

### backend/app/Providers/ (1 file)

| File | Size | Namespace |
|---|---|---|
| `AIServiceProvider.php` | ~145 lines | `App\Providers` |

---

### backend/app/Services/AI/ (1 file)

| File | Size | Namespace |
|---|---|---|
| `AIManager.php` | ~170 lines | `App\Services\AI` |

---

### backend/app/Services/AI/Concerns/ — Traits (2 files)

| File | Size | Namespace | Bug Fixed |
|---|---|---|---|
| `HasRetry.php` | ~110 lines | `App\Services\AI\Concerns` | BUG-005: named param |
| `TracksUsage.php` | ~75 lines | `App\Services\AI\Concerns` | BUG-008: cache race |

---

### backend/app/Services/AI/DTOs/ — Value Objects (5 files)

| File | Size | Namespace |
|---|---|---|
| `ChatMessage.php` | ~70 lines | `App\Services\AI\DTOs` |
| `ChatResponse.php` | ~55 lines | `App\Services\AI\DTOs` |
| `AIUsage.php` | ~75 lines | `App\Services\AI\DTOs` |
| `EmbeddingResponse.php` | ~65 lines | `App\Services\AI\DTOs` |
| `TranscriptionResponse.php` | ~55 lines | `App\Services\AI\DTOs` |

---

### backend/app/Services/AI/Exceptions/ (2 files)

| File | Size | Namespace |
|---|---|---|
| `AIProviderException.php` | ~20 lines | `App\Services\AI\Exceptions` |
| `AIRateLimitException.php` | ~28 lines | `App\Services\AI\Exceptions` |

---

### backend/app/Services/AI/Providers/ (4 files)

| File | Size | Namespace | Bugs Fixed |
|---|---|---|---|
| `ClaudeProvider.php` | ~175 lines | `App\Services\AI\Providers` | — |
| `OpenAIChatProvider.php` | ~150 lines | `App\Services\AI\Providers` | — |
| `OpenAIEmbeddingProvider.php` | ~135 lines | `App\Services\AI\Providers` | — |
| `WhisperProvider.php` | ~185 lines | `App\Services\AI\Providers` | BUG-006: multipart; BUG-007: fd leak |

---

### backend/app/Services/ — High-Level Services (5 files)

| File | Size | Namespace | Bugs Fixed |
|---|---|---|---|
| `SummarizationService.php` | ~90 lines | `App\Services` | — |
| `QuestionGenerationService.php` | ~95 lines | `App\Services` | — |
| `RAGChatService.php` | ~105 lines | `App\Services` | BUG-001: file was missing |
| `EmbeddingService.php` | ~130 lines | `App\Services` | — |
| `TranscriptionService.php` | ~70 lines | `App\Services` | BUG-002: file was missing |

---

### backend/config/ (1 file)

| File | Size | Description |
|---|---|---|
| `ai.php` | ~110 lines | Full AI provider configuration (6 sections) |

---

### backend/database/migrations/ (1 file)

| File | Size | Description |
|---|---|---|
| `2026_06_23_230621_create_ai_usage_logs_table.php` | ~65 lines | Creates `ai_usage_logs` table; 9 columns; 3 composite indexes |

---

### backend/tests/Unit/AI/ — Test Files (5 files)

| File | Tests | Coverage |
|---|---|---|
| `ClaudeProviderTest.php` | 12 | Auth, system prompt extraction, options, errors, usage, ping |
| `OpenAIEmbeddingProviderTest.php` | 13 | Batch, empty batch, size limit, cosine similarity, dim mismatch |
| `WhisperProviderTest.php` | 14 | Multipart format, file validation, extensions, error paths |
| `AIManagerTest.php` | 16 | Routing, delegation, health, registration, override |
| `SummarizationServiceTest.php` | 17 | All 7 formats, language, options, response passthrough |

---

### docs/ (1 file)

| File | Size | Sections |
|---|---|---|
| `AI_INTEGRATION_GUIDE.md` | ~746 lines | 10 sections: Bootstrap, 5 services, AIManager, Queue, Errors, Env Vars |

---

## Files Created (New in Phase 2)

All 31 PHP files and 7 Markdown files listed above were created during Phase 2. None existed prior to this release.

---

## Files Modified (During Remediation)

| File | Modification | Bug |
|---|---|---|
| `HasRetry.php` | Changed `context:` to `provider:` at fallback throw site | BUG-005 |
| `WhisperProvider.php` | Replaced `->attach()` loop with `->asMultipart()` array; added `finally { fclose() }` | BUG-006, BUG-007 |
| `TracksUsage.php` | Removed `Cache::put(Cache::get(...))` after `Cache::increment()`; added `Cache::add()` for TTL | BUG-008 |

---

## Configuration Files

### `backend/config/ai.php`

| Section | Key | Env Variable | Default |
|---|---|---|---|
| root | `default_chat_provider` | `AI_DEFAULT_CHAT_PROVIDER` | `claude` |
| `anthropic` | `api_key` | `ANTHROPIC_API_KEY` | _(required)_ |
| `anthropic` | `model` | `ANTHROPIC_MODEL` | `claude-sonnet-4-5` |
| `anthropic` | `max_tokens` | `ANTHROPIC_MAX_TOKENS` | `4096` |
| `anthropic` | `timeout` | `ANTHROPIC_TIMEOUT` | `120` |
| `anthropic` | `max_retries` | `ANTHROPIC_MAX_RETRIES` | `3` |
| `openai` | `api_key` | `OPENAI_API_KEY` | _(required)_ |
| `openai` | `chat_model` | `OPENAI_CHAT_MODEL` | `gpt-4o-mini` |
| `openai` | `embedding_model` | `OPENAI_EMBEDDING_MODEL` | `text-embedding-3-small` |
| `openai` | `whisper_model` | `OPENAI_WHISPER_MODEL` | `whisper-1` |
| `openai` | `max_tokens` | `OPENAI_MAX_TOKENS` | `4096` |
| `openai` | `timeout` | `OPENAI_TIMEOUT` | `120` |
| `openai` | `whisper_timeout` | `OPENAI_WHISPER_TIMEOUT` | `300` |
| `openai` | `max_retries` | `OPENAI_MAX_RETRIES` | `3` |
| `budget` | `claude_daily_tokens` | `AI_BUDGET_CLAUDE_DAILY_TOKENS` | `1000000` |
| `budget` | `openai_daily_tokens` | `AI_BUDGET_OPENAI_DAILY_TOKENS` | `1000000` |
| `budget` | `alert_threshold_pct` | `AI_BUDGET_ALERT_THRESHOLD_PCT` | `80` |
| `rag` | `top_k` | `RAG_TOP_K` | `5` |
| `rag` | `similarity_threshold` | `RAG_SIMILARITY_THRESHOLD` | `0.75` |
| `rag` | `max_context_tokens` | `RAG_MAX_CONTEXT_TOKENS` | `3000` |
| `prompts` | `rag_system` | `AI_PROMPT_RAG_SYSTEM` | _(Thai grounding prompt)_ |
| `prompts` | `summarize_system` | `AI_PROMPT_SUMMARIZE_SYSTEM` | _(Academic summariser prompt)_ |
| `prompts` | `question_gen_system` | `AI_PROMPT_QUESTION_GEN_SYSTEM` | _(Exam question writer prompt)_ |

---

## Environment Variables

### Required (no defaults — must be set)

```dotenv
ANTHROPIC_API_KEY=sk-ant-api03-...
OPENAI_API_KEY=sk-proj-...
```

### Recommended Overrides

```dotenv
ANTHROPIC_MODEL=claude-sonnet-4-5
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_WHISPER_MODEL=whisper-1
AI_DEFAULT_CHAT_PROVIDER=claude
```

### Full Variable List

```dotenv
# Anthropic
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-5
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_TIMEOUT=120
ANTHROPIC_MAX_RETRIES=3

# OpenAI
OPENAI_API_KEY=
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_WHISPER_MODEL=whisper-1
OPENAI_MAX_TOKENS=4096
OPENAI_TIMEOUT=120
OPENAI_WHISPER_TIMEOUT=300
OPENAI_MAX_RETRIES=3

# AI Layer
AI_DEFAULT_CHAT_PROVIDER=claude

# Budget
AI_BUDGET_CLAUDE_DAILY_TOKENS=1000000
AI_BUDGET_OPENAI_DAILY_TOKENS=1000000
AI_BUDGET_ALERT_THRESHOLD_PCT=80

# RAG
RAG_TOP_K=5
RAG_SIMILARITY_THRESHOLD=0.75
RAG_MAX_CONTEXT_TOKENS=3000
```

> ⚠️ **CRITICAL:** `OPENAI_EMBEDDING_MODEL` must not be changed after initial ChromaDB indexing without re-embedding all document chunks.

---

## Test Files

| File | Framework | Mocking | Real API Calls |
|---|---|---|---|
| `ClaudeProviderTest.php` | PHPUnit + Laravel TestCase | `Http::fake()` | None |
| `OpenAIEmbeddingProviderTest.php` | PHPUnit + Laravel TestCase | `Http::fake()` | None |
| `WhisperProviderTest.php` | PHPUnit + Laravel TestCase | `Http::fake()` + temp files | None |
| `AIManagerTest.php` | PHPUnit + Laravel TestCase | Mockery | None |
| `SummarizationServiceTest.php` | PHPUnit + Laravel TestCase | Mockery | None |

---

## Documentation Files

| File | Purpose | Location |
|---|---|---|
| `README.md` | Package overview, quick-start | `RELEASE/README.md` |
| `CHANGELOG.md` | Change history Phases 0–2 | `RELEASE/CHANGELOG.md` |
| `IMPLEMENTATION_REPORT.md` | Technical implementation detail | `RELEASE/IMPLEMENTATION_REPORT.md` |
| `VALIDATION_REPORT.md` | Test and validation results | `RELEASE/VALIDATION_REPORT.md` |
| `ACCEPTANCE_REPORT.md` | Formal acceptance audit | `RELEASE/ACCEPTANCE_REPORT.md` |
| `RELEASE_MANIFEST.md` | This file | `RELEASE/RELEASE_MANIFEST.md` |
| `AI_INTEGRATION_GUIDE.md` | Full usage documentation | `RELEASE/docs/AI_INTEGRATION_GUIDE.md` |
| `project_memory.md` | Project Single Source of Truth | `RELEASE/project_memory.md` |

---

## Known Limitations

| # | Limitation | Severity | Planned Resolution |
|---|---|---|---|
| LIM-001 | `TranscriptionProviderInterface` has no `ping()` — Whisper excluded from `healthCheck()` | Low | Phase 3 |
| LIM-002 | `TranscriptionResponse::wordCount()` uses `str_word_count()` — inaccurate for Thai text | Low | Phase 3 |
| LIM-003 | Budget thresholds in `config/ai.php` are defined but not enforced at runtime | Low | Phase 3 (`BudgetGuard`) |
| LIM-004 | `ClaudeProvider::ping()` consumes real API tokens (no free health endpoint) | Low | Operational — restrict to cron |
| LIM-005 | `OpenAIEmbeddingProvider::getDimensions()` returns hardcoded 1536; ignores `dimensions` option | Low | Phase 3 |
| LIM-006 | ChromaDB PHP client library not yet selected | Medium | Phase 3 decision required before pipeline integration |

---

## Runtime Requirements

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.2 | `readonly` properties, named arguments, fibers |
| Laravel | 12.x | `withProviders()` bootstrap API |
| Redis | 7.x | Required for `Cache::increment()` atomic counters and queue |
| MariaDB | 11.x | `ai_usage_logs` migration target |
| Composer packages | `laravel/sanctum`, `predis/predis` | Already required by Phase 1 |
| External APIs | Anthropic API, OpenAI API | Both require paid credentials |
| Queue worker | `php artisan queue:work` | Required for all AI operations |

---

## Deployment Checklist

- [ ] Copy `backend/` files into Laravel application
- [ ] Add `AIServiceProvider::class` to `bootstrap/app.php` `->withProviders([...])`
- [ ] Run `php artisan vendor:publish --tag=ai-config`
- [ ] Set `ANTHROPIC_API_KEY` and `OPENAI_API_KEY` in `.env`
- [ ] Run `php artisan migrate`
- [ ] Start queue worker: `php artisan queue:work redis --queue=ai-embed,ai-summarize,ai-transcribe,ai-chat --timeout=600 --tries=3`
- [ ] Verify: `php artisan tinker` → `app(\App\Services\AI\AIManager::class)->healthCheck()`

---

## Ready For Merge

**YES** ✅

All 34 deliverables verified. Zero runtime-blocking defects. All 8 remediation bugs confirmed fixed. Acceptance: PASS.
