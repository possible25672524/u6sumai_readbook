# Changelog — AI Provider Layer (U2)

All changes to the AI provider architecture are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2.0.0] — 2026-06-24 — AI Provider Layer (Phase 2 Complete)

### Added

**Contracts (Interfaces)**
- `AIProviderInterface` — contract for all text-generation providers (`chat`, `getProviderName`, `getDefaultModel`, `ping`)
- `EmbeddingProviderInterface` — contract for vector embedding providers (`embed`, `embedBatch`, `getEmbeddingModel`, `getDimensions`)
- `TranscriptionProviderInterface` — contract for audio transcription providers (`transcribe`, `getTranscriptionModel`)

**Provider Implementations**
- `ClaudeProvider` — Anthropic Claude Sonnet via `/v1/messages` API; primary text generation provider
- `OpenAIChatProvider` — OpenAI Chat Completions via `/v1/chat/completions`; fallback text generation
- `OpenAIEmbeddingProvider` — OpenAI `text-embedding-3-small` via `/v1/embeddings`; 1536-dimensional vectors; supports batch embedding up to 2048 texts
- `WhisperProvider` — OpenAI Whisper via `/v1/audio/transcriptions`; supports mp3/mp4/mpeg/mpga/m4a/wav/webm up to 25 MB; returns timestamps via `verbose_json`

**Dispatcher**
- `AIManager` — Strategy Pattern hub; routes `chat()`, `embed()`, `embedBatch()`, `transcribe()`, `complete()` to registered providers; supports runtime provider registration and override; includes `healthCheck()` aggregate

**DTOs (Value Objects)**
- `ChatMessage` — immutable; role validation; `toOpenAIArray()` and `toAnthropicArray()` serialisers; factory helpers (`::system()`, `::user()`, `::assistant()`)
- `ChatResponse` — normalised cross-provider response; `isComplete()` check; `toArray()` serialiser
- `AIUsage` — token counts; `fromOpenAI()` and `fromAnthropic()` factory methods; `zero()` sentinel; cost estimate support
- `EmbeddingResponse` — float vector; `norm()` utility; `cosineSimilarity()` with dimension guard
- `TranscriptionResponse` — full text, language, duration, word-level and segment-level timestamp arrays; `wordCount()` and `isEmpty()` helpers

**Exceptions**
- `AIProviderException` — base runtime exception; exposes `provider` and `statusCode`
- `AIRateLimitException` — extends base; exposes `retryAfterSeconds` from `Retry-After` header

**Cross-cutting Concerns (Traits)**
- `HasRetry` — exponential backoff with ±20% jitter; configurable `maxAttempts` and `baseDelayMs`; passes through 4xx client errors immediately; honours `Retry-After` on rate-limit responses
- `TracksUsage` — persists per-call token usage to `ai_usage_logs` via `AIUsageLog::create()`; maintains atomic daily token counter in Redis via `Cache::increment()` + `Cache::add()` (TTL-on-new-key pattern)

**High-Level Services**
- `SummarizationService` — 7 formats: `short`, `detailed`, `bullet`, `exam`, `mindmap`, `table`, `keypoints`; language options: `th`, `en`, `auto`
- `QuestionGenerationService` — 5 types: `multiple_choice`, `true_false`, `short_answer`, `fill_blank`, `essay`; JSON output; configurable count (1–20) and difficulty
- `RAGChatService` — grounded RAG chatbot; `answer()` with multi-turn history; `quickAnswer()` for Module 10; enforces "answer only from documents" via system prompt
- `EmbeddingService` — `embedDocument()` with 1500-char overlapping chunking and sentence-boundary detection; `embedQuery()` for RAG retrieval; `getModelInfo()` for model consistency validation
- `TranscriptionService` — `transcribe()` returning full `TranscriptionResponse` with timestamps; `transcribeToText()` for plain-text-only use cases

**Infrastructure**
- `AIServiceProvider` — Laravel 12 service provider; registers 12 singletons + 3 interface bindings; publishes `ai.php` config via `--tag=ai-config`
- `AIUsageLog` — Eloquent model; 3 query scopes (`forProvider`, `today`, `forUser`); 2 static aggregates (`dailyTokensForProvider`, `costForProvider`)
- `2026_06_23_230621_create_ai_usage_logs_table` migration — 9 columns; FK to `users` with `nullOnDelete()`; 3 composite indexes for cost rollup, per-user analytics, and per-feature breakdown
- `config/ai.php` — 6 sections: `default_chat_provider`, `anthropic`, `openai`, `budget`, `rag`, `prompts`; all values overridable via environment variables

**Tests**
- `ClaudeProviderTest` — 12 test methods; Http::fake() for all Anthropic API calls
- `OpenAIEmbeddingProviderTest` — 13 test methods; batch, empty-batch, size-limit, cosine-similarity, dimension-mismatch
- `WhisperProviderTest` — 14 test methods; multipart format validation, all 6 audio extensions, file size guard, error paths
- `AIManagerTest` — 16 test methods; Mockery mocks; routing, delegation, health check, provider override
- `SummarizationServiceTest` — 17 test methods; data provider for all 7 formats; language instruction, options pass-through

**Documentation**
- `docs/AI_INTEGRATION_GUIDE.md` — 10 sections; bootstrap registration; service-by-service usage examples with Queue Job patterns; error handling reference; environment variable reference
- `project_memory.md` updated — §12 added (2.1–2.8): architecture summary, 34-file manifest, provider table, bug log, known limitations, phase integration points, testing status, bootstrap snippet

---

### Fixed (Remediation — during Phase 2)

| # | Bug | Severity |
|---|---|---|
| BUG-001 | `RAGChatService.php` missing — `AIServiceProvider` would fatal on boot | FATAL |
| BUG-002 | `TranscriptionService.php` missing — `AIServiceProvider` would fatal on boot | FATAL |
| BUG-003 | `AIUsageLog` model missing — `TracksUsage::recordUsage()` would throw class-not-found | FATAL |
| BUG-004 | `ai_usage_logs` migration missing — first AI call would throw `QueryException` | FATAL |
| BUG-005 | `HasRetry` fallback throw used `context:` named param not present on `AIProviderException` | HIGH |
| BUG-006 | `WhisperProvider` sent form fields via `->attach()` causing OpenAI to reject multipart request | HIGH |
| BUG-007 | `WhisperProvider` `fopen()` handle never closed — file descriptor leak under concurrency | MEDIUM |
| BUG-008 | `TracksUsage` used non-atomic `Cache::put(Cache::get(...))` after `Cache::increment()` — race condition | MEDIUM |

---

## [1.0.0] — 2026-06-23 — Backend Foundation (Phase 1 Complete)

### Added
- Laravel 12 project scaffold via Docker Compose
- Authentication + RBAC via Laravel Sanctum (Bearer token)
- `roles`, `permissions`, `role_permissions` tables with seeder
- 8 Auth API endpoints (`/auth/register`, `/auth/login`, `/auth/logout`, `/auth/me`, etc.)
- `users.is_active` flag for Phase 7 account suspension
- Default roles: admin / teacher / student
- `backend/docs/API_AUTH.md` — Auth API specification

---

## [0.1.0] — 2026-06-22 — Project Setup (Phase 0 Complete)

### Added
- Docker Compose stack: Laravel, MariaDB 11, Redis 7, ChromaDB, MinIO, Tesseract OCR
- Environment configuration templates
- Nginx backend proxy configuration
- React + Vite + PWA frontend scaffold (routing, Zustand state, API client, 14-module placeholder pages)
- Queue worker container (`php artisan queue:work --tries=3 --timeout=600`)
- MinIO bucket auto-initialisation via `minio-init` service
