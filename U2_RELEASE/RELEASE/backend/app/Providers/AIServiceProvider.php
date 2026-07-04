<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AI\AIProviderInterface;
use App\Contracts\AI\EmbeddingProviderInterface;
use App\Contracts\AI\TranscriptionProviderInterface;
use App\Services\AI\AIManager;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\OpenAIChatProvider;
use App\Services\AI\Providers\OpenAIEmbeddingProvider;
use App\Services\AI\Providers\WhisperProvider;
use App\Services\EmbeddingService;
use App\Services\QuestionGenerationService;
use App\Services\RAGChatService;
use App\Services\SummarizationService;
use App\Services\TranscriptionService;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all AI-related services into the Laravel DI container.
 *
 * To add to bootstrap/app.php:
 *   ->withProviders([App\Providers\AIServiceProvider::class])
 */
class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerProviders();
        $this->registerManager();
        $this->registerHighLevelServices();
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/ai.php' => config_path('ai.php'),
        ], 'ai-config');
    }

    // ── Provider Bindings ────────────────────────────────────────────────

    private function registerProviders(): void
    {
        // Claude provider (primary text generation)
        $this->app->singleton(ClaudeProvider::class, function () {
            return new ClaudeProvider(
                apiKey:         config('ai.anthropic.api_key'),
                model:          config('ai.anthropic.model'),
                maxTokens:      (int) config('ai.anthropic.max_tokens'),
                timeoutSeconds: (int) config('ai.anthropic.timeout'),
                maxRetries:     (int) config('ai.anthropic.max_retries'),
            );
        });

        // OpenAI Chat provider (alternative / fallback)
        $this->app->singleton(OpenAIChatProvider::class, function () {
            return new OpenAIChatProvider(
                apiKey:         config('ai.openai.api_key'),
                model:          config('ai.openai.chat_model'),
                maxTokens:      (int) config('ai.openai.max_tokens'),
                timeoutSeconds: (int) config('ai.openai.timeout'),
                maxRetries:     (int) config('ai.openai.max_retries'),
            );
        });

        // OpenAI Embedding provider (ONLY embedding model — never swap without re-indexing)
        $this->app->singleton(OpenAIEmbeddingProvider::class, function () {
            return new OpenAIEmbeddingProvider(
                apiKey:         config('ai.openai.api_key'),
                model:          config('ai.openai.embedding_model'),
                timeoutSeconds: (int) config('ai.openai.timeout'),
                maxRetries:     (int) config('ai.openai.max_retries'),
            );
        });

        // Whisper provider (audio transcription)
        $this->app->singleton(WhisperProvider::class, function () {
            return new WhisperProvider(
                apiKey:         config('ai.openai.api_key'),
                model:          config('ai.openai.whisper_model'),
                timeoutSeconds: (int) config('ai.openai.whisper_timeout'),
                maxRetries:     (int) config('ai.openai.max_retries'),
            );
        });

        // Interface → concrete bindings (used for type-hinted injection)
        $this->app->bind(EmbeddingProviderInterface::class, OpenAIEmbeddingProvider::class);
        $this->app->bind(TranscriptionProviderInterface::class, WhisperProvider::class);

        // Default AIProviderInterface binding (Claude)
        $this->app->bind(AIProviderInterface::class, ClaudeProvider::class);
    }

    // ── AIManager ────────────────────────────────────────────────────────

    private function registerManager(): void
    {
        $this->app->singleton(AIManager::class, function ($app) {
            $manager = new AIManager(
                embeddingProvider:      $app->make(OpenAIEmbeddingProvider::class),
                transcriptionProvider:  $app->make(WhisperProvider::class),
                defaultChatProvider:    config('ai.default_chat_provider', 'claude'),
            );

            // Register all available chat providers
            $manager->registerChatProvider('claude', $app->make(ClaudeProvider::class));
            $manager->registerChatProvider('openai', $app->make(OpenAIChatProvider::class));

            return $manager;
        });
    }

    // ── High-Level Services ──────────────────────────────────────────────

    private function registerHighLevelServices(): void
    {
        $this->app->singleton(SummarizationService::class, function ($app) {
            return new SummarizationService($app->make(AIManager::class));
        });

        $this->app->singleton(QuestionGenerationService::class, function ($app) {
            return new QuestionGenerationService($app->make(AIManager::class));
        });

        $this->app->singleton(RAGChatService::class, function ($app) {
            return new RAGChatService($app->make(AIManager::class));
        });

        $this->app->singleton(EmbeddingService::class, function ($app) {
            return new EmbeddingService($app->make(AIManager::class));
        });

        $this->app->singleton(TranscriptionService::class, function ($app) {
            return new TranscriptionService($app->make(AIManager::class));
        });
    }
}
