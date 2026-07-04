<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the ai_usage_logs table.
 *
 * Stores per-call token usage for every AI API request.
 * Used by TracksUsage trait and AIUsageLog model.
 *
 * Index strategy:
 *   - (provider, created_at)  → daily/monthly cost rollups per provider
 *   - (user_id, created_at)   → per-user usage analytics
 *   - (operation, created_at) → usage breakdown by feature (summarize, embed, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();

            // Which AI provider and model handled the request
            $table->string('provider', 32)->comment('claude | openai');
            $table->string('model', 100)->comment('e.g. claude-sonnet-4-5, text-embedding-3-small');

            // Which product feature triggered the call
            $table->string('operation', 100)->comment('e.g. summarize:bullet, embed, transcribe, rag_chat');

            // Nullable: system-initiated jobs (OCR pipeline) have no user
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Token counts — always recorded even when zero (e.g. Whisper has no token count)
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Cost estimate in USD, null when the provider doesn't expose pricing
            $table->decimal('estimated_cost_usd', 12, 6)->nullable();

            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────────────
            // Daily cost rollup per provider (most common query in TracksUsage / budget alerts)
            $table->index(['provider', 'created_at'], 'ai_usage_provider_date');

            // Per-user analytics (Module 13 — Analytics)
            $table->index(['user_id', 'created_at'], 'ai_usage_user_date');

            // Per-feature breakdown
            $table->index(['operation', 'created_at'], 'ai_usage_operation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
