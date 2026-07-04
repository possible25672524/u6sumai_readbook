<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Chat Provider
    |--------------------------------------------------------------------------
    |
    | Which provider AIManager::chat() uses by default.
    | Options: "claude" | "openai"
    |
    */

    'default_chat_provider' => env('AI_DEFAULT_CHAT_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude) Configuration
    |--------------------------------------------------------------------------
    |
    | Used for: summarisation, question generation, RAG chatbot responses.
    | Model: claude-sonnet-4-5 (confirm version in .env before going to prod)
    |
    */

    'anthropic' => [
        'api_key'     => env('ANTHROPIC_API_KEY', ''),
        'model'       => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'max_tokens'  => (int) env('ANTHROPIC_MAX_TOKENS', 4096),
        'timeout'     => (int) env('ANTHROPIC_TIMEOUT', 120),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Used for:
    |   - chat_model:      Alternative/fallback text generation
    |   - embedding_model: Document chunk & query embeddings (text-embedding-3-small)
    |   - whisper_model:   Audio transcription (whisper-1)
    |
    | CRITICAL: Never change embedding_model without re-indexing ALL chunks in ChromaDB.
    |
    */

    'openai' => [
        'api_key'        => env('OPENAI_API_KEY', ''),
        'chat_model'     => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
        'embedding_model'=> env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'whisper_model'  => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
        'max_tokens'     => (int) env('OPENAI_MAX_TOKENS', 4096),
        'timeout'        => (int) env('OPENAI_TIMEOUT', 120),
        'whisper_timeout'=> (int) env('OPENAI_WHISPER_TIMEOUT', 300),  // audio files take longer
        'max_retries'    => (int) env('OPENAI_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Budget & Usage Alerts
    |--------------------------------------------------------------------------
    |
    | Daily token budget per provider. When exceeded, a warning is logged.
    | Set to 0 to disable budget tracking.
    |
    */

    'budget' => [
        'claude_daily_tokens'  => (int) env('AI_BUDGET_CLAUDE_DAILY_TOKENS', 1_000_000),
        'openai_daily_tokens'  => (int) env('AI_BUDGET_OPENAI_DAILY_TOKENS', 1_000_000),
        'alert_threshold_pct'  => (int) env('AI_BUDGET_ALERT_THRESHOLD_PCT', 80),  // % before alert
    ],

    /*
    |--------------------------------------------------------------------------
    | RAG (Retrieval-Augmented Generation) Settings
    |--------------------------------------------------------------------------
    |
    | Controls how the RAG chatbot retrieves context from ChromaDB.
    |
    */

    'rag' => [
        'top_k'              => (int) env('RAG_TOP_K', 5),            // number of chunks to retrieve
        'similarity_threshold'=> (float) env('RAG_SIMILARITY_THRESHOLD', 0.75),
        'max_context_tokens' => (int) env('RAG_MAX_CONTEXT_TOKENS', 3000),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    |
    | Centralised system prompt snippets. Override in env for A/B testing.
    | The RAG chatbot system prompt enforces "answer only from documents".
    |
    */

    'prompts' => [
        'rag_system' => env('AI_PROMPT_RAG_SYSTEM',
            "You are an AI study assistant. Answer the user's question ONLY using the provided document excerpts. " .
            "If the answer is not contained in the excerpts, reply: 'ไม่พบข้อมูลนี้ในเอกสารที่อัปโหลด' " .
            "(Information not found in uploaded documents). Never use general knowledge outside the documents."
        ),

        'summarize_system' => env('AI_PROMPT_SUMMARIZE_SYSTEM',
            'You are an expert academic summarizer. Produce clear, accurate summaries in the same language as the input text.'
        ),

        'question_gen_system' => env('AI_PROMPT_QUESTION_GEN_SYSTEM',
            'You are an expert exam question writer. Generate high-quality questions strictly based on the provided content.'
        ),
    ],

];
