<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

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

    // ─── OpenAI ───────────────────────────────────────────────────
    'openai' => [
        'key'             => env('OPENAI_API_KEY', ''),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'whisper_model'   => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
    ],

    // ─── Anthropic (Claude Sonnet) ────────────────────────────────
    'anthropic' => [
        'key'   => env('ANTHROPIC_API_KEY', ''),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
    ],

    // ─── Tesseract OCR ────────────────────────────────────────────
    'tesseract' => [
        'binary'    => env('TESSERACT_BIN', '/usr/bin/tesseract'),
        'languages' => env('TESSERACT_LANGUAGES', 'tha+eng'),
    ],

    // ─── ChromaDB Vector Database ─────────────────────────────────
    'chromadb' => [
        'url'        => env('CHROMA_URL', 'http://chromadb:8000'),
        'collection' => env('CHROMA_COLLECTION', 'study_assistant_docs'),
    ],

    // ─── MinIO Object Storage ─────────────────────────────────────
    // Note: configured via filesystems.php / AWS_* env vars
    // FILESYSTEM_DISK=s3 in .env
];
