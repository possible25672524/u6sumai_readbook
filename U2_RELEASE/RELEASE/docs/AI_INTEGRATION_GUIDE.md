# AI Provider Layer — Integration Guide

**Version:** Phase 2 (U2 deliverable)
**Stack:** Laravel 12, Claude Sonnet, OpenAI Embedding + Whisper
**Last updated:** 2026-06-24

---

## Table of Contents

1. [Bootstrap Registration](#1-bootstrap-registration)
2. [SummarizationService](#2-summarizationservice)
3. [QuestionGenerationService](#3-questiongenerationservice)
4. [EmbeddingService](#4-embeddingservice)
5. [RAGChatService](#5-ragchatservice)
6. [TranscriptionService](#6-transcriptionservice)
7. [Direct AIManager Usage](#7-direct-aimanager-usage)
8. [Queue Job Integration](#8-queue-job-integration)
9. [Error Handling Reference](#9-error-handling-reference)
10. [Environment Variables](#10-environment-variables)

---

## 1. Bootstrap Registration

### Step 1 — Register AIServiceProvider

In `bootstrap/app.php`, add `AIServiceProvider` to the providers list:

```php
<?php
// bootstrap/app.php

use Illuminate\Foundation\Application;
use App\Providers\AIServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withProviders([
        // ... existing providers ...
        AIServiceProvider::class,   // ← add this line
    ])
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();
```

### Step 2 — Publish the config

```bash
php artisan vendor:publish --tag=ai-config
```

This copies `config/ai.php` into your application's `config/` directory.

### Step 3 — Set environment variables

Copy the required keys into your `.env` file (see full list in [Section 10](#10-environment-variables)).

```bash
ANTHROPIC_API_KEY=sk-ant-api03-...
OPENAI_API_KEY=sk-proj-...
```

### Step 4 — Run the migration

```bash
php artisan migrate
```

This creates the `ai_usage_logs` table for token usage tracking.

### What gets registered

`AIServiceProvider` binds the following singletons into the container:

| Binding | Concrete Class | Purpose |
|---|---|---|
| `ClaudeProvider::class` | `ClaudeProvider` | Text generation (primary) |
| `OpenAIChatProvider::class` | `OpenAIChatProvider` | Text generation (fallback) |
| `OpenAIEmbeddingProvider::class` | `OpenAIEmbeddingProvider` | Embeddings |
| `WhisperProvider::class` | `WhisperProvider` | Audio transcription |
| `AIProviderInterface::class` | → `ClaudeProvider` | Default chat interface |
| `EmbeddingProviderInterface::class` | → `OpenAIEmbeddingProvider` | Embedding interface |
| `TranscriptionProviderInterface::class` | → `WhisperProvider` | Transcription interface |
| `AIManager::class` | `AIManager` | Central dispatcher (Strategy Pattern) |
| `SummarizationService::class` | `SummarizationService` | Module 5 |
| `QuestionGenerationService::class` | `QuestionGenerationService` | Module 7 |
| `RAGChatService::class` | `RAGChatService` | Module 9 |
| `EmbeddingService::class` | `EmbeddingService` | Module 4 (Pipeline) |
| `TranscriptionService::class` | `TranscriptionService` | Module 4 (Pipeline) |

### Provider Resolution Flow

```
HTTP Request / Queue Job
         │
         ▼
   AIServiceProvider (registers all singletons on boot)
         │
         ▼
      AIManager  ◄────── resolves via app(AIManager::class) or DI
      /    |    \
     /     |     \
ClaudeProvider  OpenAIEmbeddingProvider  WhisperProvider
(chat)          (embed)                  (transcribe)
```

---

## 2. SummarizationService

**Module 5 — 7 summary formats**

### Injection (recommended)

```php
<?php

namespace App\Jobs;

use App\Services\SummarizationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SummarizeDocumentJob implements ShouldQueue
{
    public $timeout = 120;
    public $tries   = 3;

    public function __construct(
        private readonly int    $documentId,
        private readonly string $content,
        private readonly int    $userId,
    ) {}

    public function handle(SummarizationService $summarizer): void
    {
        // ── Bullet-point summary (default) ───────────────────────────
        $response = $summarizer->summarize(
            content:  $this->content,
            format:   'bullet',
            language: 'th',       // 'th' | 'en' | 'auto'
            userId:   $this->userId,
        );

        // $response->content  = the generated summary text
        // $response->usage    = AIUsage DTO (token counts)
        // $response->provider = "claude"
        // $response->model    = "claude-sonnet-4-5"

        Summary::create([
            'document_id' => $this->documentId,
            'type'        => 'bullet',
            'content'     => $response->content,
        ]);
    }
}
```

### All 7 formats

```php
$formats = $summarizer->availableFormats();
// Returns:
// [
//   'short'     => 'Write a concise 2–3 sentence summary...',
//   'detailed'  => 'Write a comprehensive multi-paragraph summary...',
//   'bullet'    => 'Write a structured bullet-point summary...',
//   'exam'      => 'Identify and list the most important facts...',
//   'mindmap'   => 'Output a text-based mind-map...',
//   'table'     => 'Extract key information and present it as a Markdown table...',
//   'keypoints' => 'List the 5 most critical key points...',
// ]

// Generate all 7 formats for a document
foreach (array_keys($formats) as $format) {
    $response = $summarizer->summarize($content, $format, 'auto', $userId);

    Summary::updateOrCreate(
        ['document_id' => $documentId, 'type' => $format],
        ['content' => $response->content],
    );
}
```

### Via facade / helper

```php
// Resolve from container without injection
$summarizer = app(SummarizationService::class);
$response   = $summarizer->summarize($text, 'exam');
```

### Token usage inspection

```php
$response = $summarizer->summarize($content, 'detailed', 'th', $userId);

$tokens = $response->usage;
// $tokens->promptTokens      — input tokens sent to Claude
// $tokens->completionTokens  — output tokens generated
// $tokens->totalTokens       — sum
```

---

## 3. QuestionGenerationService

**Module 7 — 5 question types, JSON output**

### Basic usage in a Queue Job

```php
<?php

use App\Services\QuestionGenerationService;

class GenerateQuestionsJob implements ShouldQueue
{
    public $timeout = 180;

    public function __construct(
        private readonly string $content,
        private readonly int    $documentId,
        private readonly int    $userId,
    ) {}

    public function handle(QuestionGenerationService $generator): void
    {
        // Generate 10 medium-difficulty multiple-choice questions
        $response = $generator->generate(
            content:    $this->content,
            type:       'multiple_choice',
            count:      10,
            difficulty: 'medium',
            userId:     $this->userId,
        );

        // Response content is a JSON string — parse it
        $questions = json_decode($response->content, true);

        // Each item: {question, options[A-D], correct, explanation, page_ref}
        foreach ($questions as $q) {
            Question::create([
                'document_id' => $this->documentId,
                'type'        => 'multiple_choice',
                'content'     => $q['question'],
                'options'     => $q['options'],
                'correct'     => $q['correct'],
                'explanation' => $q['explanation'],
                'page_ref'    => $q['page_ref'],
            ]);
        }
    }
}
```

### All 5 question types

```php
// multiple_choice → {question, options[A,B,C,D], correct, explanation, page_ref}
$mc = $generator->generate($content, 'multiple_choice', 5, 'hard');

// true_false → {question, answer: bool, explanation, page_ref}
$tf = $generator->generate($content, 'true_false', 10, 'easy');

// short_answer → {question, sample_answer, keywords[], page_ref}
$sa = $generator->generate($content, 'short_answer', 5, 'medium');

// fill_blank → {sentence, answer, context, page_ref}
$fb = $generator->generate($content, 'fill_blank', 8, 'easy');

// essay → {question, marking_criteria[], suggested_points[], page_ref}
$es = $generator->generate($content, 'essay', 3, 'hard');

// Available types list
$types = $generator->availableTypes();
// ['multiple_choice', 'true_false', 'short_answer', 'fill_blank', 'essay']
```

### Error handling for malformed JSON

```php
$response = $generator->generate($content, 'multiple_choice', 5);

$questions = json_decode($response->content, true);

if (json_last_error() !== JSON_ERROR_NONE || ! is_array($questions)) {
    // Claude occasionally wraps output in markdown code fences despite instructions.
    // Strip them and retry parsing:
    $cleaned   = preg_replace('/^```(?:json)?\n?|\n?```$/m', '', trim($response->content));
    $questions = json_decode($cleaned, true) ?? [];
}
```

---

## 4. EmbeddingService

**Module 4 (Pipeline) — Document chunking + vector embedding**

### Embedding a document after OCR/transcription

```php
<?php

use App\Services\EmbeddingService;

class EmbedDocumentJob implements ShouldQueue
{
    public $timeout = 600;   // Large documents take time
    public $tries   = 3;

    public function __construct(
        private readonly string $documentId,
        private readonly string $extractedText,
        private readonly int    $userId,
    ) {}

    public function handle(EmbeddingService $embedder): void
    {
        // Chunk the document and embed all chunks in batch
        $embeddedChunks = $embedder->embedDocument(
            text:       $this->extractedText,
            documentId: $this->documentId,
            userId:     $this->userId,
        );

        // Each chunk:
        // [
        //   'chunk'       => string (the text chunk),
        //   'embedding'   => float[] (1536-dim vector),
        //   'index'       => int (chunk sequence number),
        //   'char_start'  => int,
        //   'char_end'    => int,
        //   'document_id' => string,
        //   'model'       => 'text-embedding-3-small',
        //   'dimensions'  => 1536,
        // ]

        // Store in ChromaDB (pseudo-code — ChromaDB client TBD in Phase 2)
        foreach ($embeddedChunks as $chunk) {
            $chromaClient->upsert(
                collection: 'documents',
                id:         "{$this->documentId}_{$chunk['index']}",
                embedding:  $chunk['embedding'],
                metadata:   [
                    'document_id' => $this->documentId,
                    'char_start'  => $chunk['char_start'],
                    'char_end'    => $chunk['char_end'],
                    'text'        => $chunk['chunk'],
                ],
            );

            // Also persist chunk mapping to MariaDB
            DocumentChunk::create([
                'document_id'  => $this->documentId,
                'chunk_index'  => $chunk['index'],
                'content'      => $chunk['chunk'],
                'chroma_id'    => "{$this->documentId}_{$chunk['index']}",
                'embedding_model' => $chunk['model'],
            ]);
        }
    }
}
```

### Embedding a query for RAG retrieval

```php
use App\Services\EmbeddingService;

// In RAG pipeline — embed the user's question to search ChromaDB
$embedder    = app(EmbeddingService::class);
$queryResult = $embedder->embedQuery($userQuestion, $userId);

// $queryResult->vector     = float[] (1536 dimensions)
// $queryResult->model      = 'text-embedding-3-small'
// $queryResult->dimensions = 1536

// Then query ChromaDB with the vector (pseudo-code)
$chunks = $chromaClient->query(
    collection: 'documents',
    queryEmbedding: $queryResult->vector,
    nResults: config('ai.rag.top_k'),
    where: ['document_id' => $documentId],
);
```

### Model info validation

```php
// Verify the embedding model hasn't changed (safety check before re-indexing)
$info = $embedder->getModelInfo();
// ['model' => 'text-embedding-3-small', 'dimensions' => 1536]

if ($info['model'] !== 'text-embedding-3-small') {
    throw new \RuntimeException(
        'Embedding model mismatch — all chunks must be re-indexed before switching models.'
    );
}
```

---

## 5. RAGChatService

**Module 9 — Grounded chatbot, answers only from uploaded documents**

### Multi-turn conversation

```php
<?php

use App\Services\RAGChatService;
use App\Services\EmbeddingService;

class ChatController extends Controller
{
    public function __construct(
        private readonly RAGChatService $chatService,
        private readonly EmbeddingService $embedder,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $question  = $request->validated('question');
        $sessionId = $request->validated('session_id');
        $userId    = $request->user()->id;

        // Step 1: Embed the question
        $queryEmbedding = $this->embedder->embedQuery($question, $userId);

        // Step 2: Retrieve top-K relevant chunks from ChromaDB (pseudo-code)
        $retrievedChunks = $chromaClient->query(
            queryEmbedding: $queryEmbedding->vector,
            nResults:       config('ai.rag.top_k', 5),
        );
        $contextTexts = array_column($retrievedChunks, 'text');

        // Step 3: Load conversation history
        $history = ChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Step 4: Generate grounded answer
        $response = $this->chatService->answer(
            question:      $question,
            contextChunks: $contextTexts,
            history:       $history,
            language:      'th',
            userId:        $userId,
        );

        // Step 5: Persist the exchange
        ChatMessage::insert([
            ['session_id' => $sessionId, 'role' => 'user',      'content' => $question],
            ['session_id' => $sessionId, 'role' => 'assistant', 'content' => $response->content],
        ]);

        return response()->json([
            'answer'   => $response->content,
            'complete' => $response->isComplete(),
            'usage'    => $response->usage->toArray(),
        ]);
    }
}
```

### Quick Answer Mode (Module 10 — low-latency, no history)

```php
// Single-turn, no conversation history, optimised for speed
$response = $chatService->quickAnswer(
    question:      'อะไรคือสาเหตุหลักของสงครามโลกครั้งที่ 2?',
    contextChunks: $retrievedChunks,
    userId:        $userId,
);

echo $response->content;
```

### When no relevant context is found

```php
// If ChromaDB returns no chunks above the similarity threshold,
// pass an empty array. RAGChatService gracefully returns the
// "not found in documents" message defined in config/ai.php.
$response = $chatService->answer(
    question:      $question,
    contextChunks: [],   // no context retrieved
    userId:        $userId,
);

// Response will contain:
// "ไม่พบข้อมูลนี้ในเอกสารที่อัปโหลด"
// (Information not found in uploaded documents)
```

---

## 6. TranscriptionService

**Module 4 (Pipeline) — Audio/video to text via Whisper**

### Basic transcription in a Queue Job

```php
<?php

use App\Services\TranscriptionService;

class TranscribeAudioJob implements ShouldQueue
{
    public $timeout = 300;   // Audio transcription can take minutes
    public $tries   = 3;
    public $backoff = [30, 60, 120];  // Exponential backoff on retry

    public function __construct(
        private readonly int    $documentId,
        private readonly string $storagePath,   // MinIO path
        private readonly int    $userId,
    ) {}

    public function handle(TranscriptionService $transcriber): void
    {
        // Download from MinIO to a local temp path first
        $localPath = storage_path("app/tmp/audio_{$this->documentId}.mp3");
        Storage::disk('minio')->copy($this->storagePath, $localPath);

        try {
            // Full transcription with timestamps
            $result = $transcriber->transcribe(
                filePath: $localPath,
                language: 'th',
                prompt:   'คำศัพท์เฉพาะทาง วิทยาศาสตร์ ชีววิทยา',  // domain hint
                userId:   $this->userId,
            );

            // $result->text            = full transcription string
            // $result->language        = 'th'
            // $result->durationSeconds = 185.4
            // $result->words           = [{word, start, end}, ...]
            // $result->segments        = [{id, start, end, text, ...}, ...]

            Transcript::create([
                'document_id'      => $this->documentId,
                'content'          => $result->text,
                'language'         => $result->language,
                'duration_seconds' => $result->durationSeconds,
                'word_count'       => $result->wordCount(),
            ]);

        } finally {
            // Always clean up the temp file
            @unlink($localPath);
        }
    }
}
```

### Plain text only (no timestamps)

```php
// When segment data is not needed (e.g. short clips)
$text = $transcriber->transcribeToText(
    filePath: '/tmp/lecture.mp3',
    language: 'en',
    userId:   $userId,
);

echo $text; // "Today we're going to discuss..."
```

### Supported formats and limits

```php
// Supported: mp3, mp4, mpeg, mpga, m4a, wav, webm
// Max size:  25 MB (WhisperProvider validates before uploading)

// These throw \InvalidArgumentException immediately (no API call made):
$transcriber->transcribe('/tmp/document.pdf');      // Wrong extension
$transcriber->transcribe('/tmp/huge_audio.wav');    // > 25 MB
$transcriber->transcribe('/tmp/missing.mp3');       // File not found
```

---

## 7. Direct AIManager Usage

For cases where you need provider-level control or cross-cutting access:

```php
use App\Services\AI\AIManager;
use App\Services\AI\DTOs\ChatMessage;

$ai = app(AIManager::class);

// ── Chat ──────────────────────────────────────────────────────────────────
// Using default provider (Claude)
$response = $ai->chat([
    ChatMessage::system('You are a helpful assistant.'),
    ChatMessage::user('Explain Newton\'s first law in Thai.'),
]);

// Using OpenAI as fallback
$response = $ai->chat($messages, [], provider: 'openai');

// Convenience single-turn
$response = $ai->complete(
    prompt:       'สรุปบทความนี้ใน 3 ประโยค: ' . $text,
    systemPrompt: 'คุณเป็นผู้ช่วยสรุปบทความ',
);

// ── Embedding ─────────────────────────────────────────────────────────────
$embedding = $ai->embed('ระบบประสาทส่วนกลาง');
// $embedding->vector     = [0.0023, -0.0041, ...] (1536 floats)
// $embedding->dimensions = 1536

$embeddings = $ai->embedBatch(['text one', 'text two', 'text three']);
// Returns EmbeddingResponse[]

// ── Transcription ─────────────────────────────────────────────────────────
$transcript = $ai->transcribe('/tmp/audio.mp3', 'th');

// ── Health Check ──────────────────────────────────────────────────────────
$health = $ai->healthCheck();
// ['chat:claude' => true, 'chat:openai' => true, 'embedding' => true]

// ── Model Info ────────────────────────────────────────────────────────────
$ai->getDefaultChatProvider();   // 'claude'
$ai->getEmbeddingModel();        // 'text-embedding-3-small'
$ai->getEmbeddingDimensions();   // 1536
```

---

## 8. Queue Job Integration

All AI operations that may run longer than 2 seconds **must** use Queue Jobs:

```php
// config/queue.php — recommended Redis queues for AI work
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'queues' => [
            'ai-embed',       // Embedding jobs (fast, batched)
            'ai-summarize',   // Summarisation (medium)
            'ai-questions',   // Question generation (medium)
            'ai-transcribe',  // Whisper transcription (slow, long timeout)
            'ai-chat',        // RAG chat (fastest — interactive)
            'default',
        ],
    ],
],

// Dispatch with the correct queue
EmbedDocumentJob::dispatch($documentId, $text, $userId)
    ->onQueue('ai-embed');

TranscribeAudioJob::dispatch($documentId, $path, $userId)
    ->onQueue('ai-transcribe');

// Start workers with appropriate timeouts
// php artisan queue:work --queue=ai-transcribe --timeout=600 --tries=3
// php artisan queue:work --queue=ai-embed --timeout=120 --tries=3
// php artisan queue:work --queue=ai-chat --timeout=60 --tries=2
```

---

## 9. Error Handling Reference

```php
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;

try {
    $response = $summarizer->summarize($content, 'bullet');

} catch (AIRateLimitException $e) {
    // Provider returned 429 — retry after the suggested delay
    // HasRetry handles this automatically within the provider.
    // If it bubbles up here, all 3 retries were exhausted.
    Log::warning('AI rate limit exhausted', [
        'provider'    => $e->provider,
        'retry_after' => $e->retryAfterSeconds,
    ]);
    // Re-dispatch the job after a delay
    SummarizeDocumentJob::dispatch($documentId, $content, $userId)
        ->delay(now()->addSeconds($e->retryAfterSeconds ?? 60));

} catch (AIProviderException $e) {
    // Any other provider failure (5xx, auth error, connection timeout)
    Log::error('AI provider failure', [
        'provider'    => $e->provider,
        'status_code' => $e->statusCode,
        'message'     => $e->getMessage(),
    ]);
    // Mark document processing as failed
    Document::find($documentId)?->update(['status' => 'failed']);

} catch (\InvalidArgumentException $e) {
    // Bad input (wrong file type, unsupported format, etc.) — do not retry
    Log::error('Invalid AI input', ['message' => $e->getMessage()]);
}
```

---

## 10. Environment Variables

Add all of the following to your `.env` file:

```dotenv
# ── Anthropic (Claude) ────────────────────────────────────────────────────
ANTHROPIC_API_KEY=sk-ant-api03-...
ANTHROPIC_MODEL=claude-sonnet-4-5
ANTHROPIC_MAX_TOKENS=4096
ANTHROPIC_TIMEOUT=120
ANTHROPIC_MAX_RETRIES=3

# ── OpenAI (Embedding + Whisper + optional Chat) ──────────────────────────
OPENAI_API_KEY=sk-proj-...
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_WHISPER_MODEL=whisper-1
OPENAI_MAX_TOKENS=4096
OPENAI_TIMEOUT=120
OPENAI_WHISPER_TIMEOUT=300
OPENAI_MAX_RETRIES=3

# ── AI Layer Config ────────────────────────────────────────────────────────
AI_DEFAULT_CHAT_PROVIDER=claude

# ── Budget Alerts ──────────────────────────────────────────────────────────
AI_BUDGET_CLAUDE_DAILY_TOKENS=1000000
AI_BUDGET_OPENAI_DAILY_TOKENS=1000000
AI_BUDGET_ALERT_THRESHOLD_PCT=80

# ── RAG Settings ───────────────────────────────────────────────────────────
RAG_TOP_K=5
RAG_SIMILARITY_THRESHOLD=0.75
RAG_MAX_CONTEXT_TOKENS=3000
```

> **CRITICAL:** Never commit `.env` to version control.
> `OPENAI_EMBEDDING_MODEL` must not change after initial indexing without re-embedding all ChromaDB vectors.
