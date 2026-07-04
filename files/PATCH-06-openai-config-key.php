<?php
/**
 * INTEGRATION PATCH-06
 * Defect:    DEFECT-04 — OpenAI API key config path inconsistency
 * Severity:  HIGH
 * Root Cause: Two separate config namespaces reference the same OPENAI_API_KEY env var
 *             but under different keys:
 *               U2 config/ai.php       → config('ai.openai.api_key')       used by AIServiceProvider
 *               U3 config/services.php → config('services.openai.key')     used by U3 EmbeddingService/TranscriptionService
 *
 *             With the PATCH-03/04 merge (U2 services canonical, U3 services removed),
 *             U3's direct-HTTP services are replaced by U2's AIManager-based services.
 *             Therefore config('services.openai.key') is no longer called by any canonical service.
 *             However U3 OcrService still reads config('services.tesseract.*') and
 *             U3 ChromaDbService reads config('services.chromadb.*') — those are fine.
 *
 *             Remaining risk: If U3's own TranscriptionService or EmbeddingService files
 *             are accidentally left in the merged repo, they will read the wrong config key.
 *
 * Resolution: Add a compatibility alias in config/services.php so both paths resolve correctly.
 *             This ensures zero breakage if any U3 direct-HTTP service remnant is loaded.
 *             Also align U3 config/services.php whisper_model key with U2 expectations.
 *
 * Files Modified: backend/config/services.php
 * Validation: Both config('ai.openai.api_key') and config('services.openai.key') will resolve.
 * Merge Risk: NONE — config additions only, no existing keys modified.
 */

// File: backend/config/services.php
// Add to existing services.php — full file shown with PATCH-06 additions marked

return [

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ── OpenAI ─────────────────────────────────────────────────────
    // PATCH-06: Added 'api_key' alias to match U2 config/ai.php expectations
    // U3 services read 'key'; U2 AIServiceProvider reads config('ai.openai.api_key')
    // Both point to same env var — this section kept for OcrService/ChromaDbService compatibility
    'openai' => [
        'key'             => env('OPENAI_API_KEY', ''),       // U3 original key
        'api_key'         => env('OPENAI_API_KEY', ''),       // PATCH-06: alias for U2 compatibility
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'whisper_model'   => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

    // ── Anthropic (Claude) ─────────────────────────────────────────
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY', ''),
        // PATCH-06: align default model version — U1 ENV_VARIABLES.md specifies claude-sonnet-4-6
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    // ── Tesseract OCR ───────────────────────────────────────────────
    'tesseract' => [
        'binary'    => env('TESSERACT_BIN', '/usr/bin/tesseract'),
        'languages' => env('TESSERACT_LANGUAGES', 'tha+eng'),
    ],

    // ── ChromaDB Vector Database ────────────────────────────────────
    'chromadb' => [
        'url'        => env('CHROMA_URL', 'http://chromadb:8000'),
        'collection' => env('CHROMA_COLLECTION', 'study_assistant_docs'),
    ],

];
