<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Contracts\AI\AIProviderInterface;
use App\Contracts\AI\EmbeddingProviderInterface;
use App\Contracts\AI\TranscriptionProviderInterface;
use App\Services\AI\AIManager;
use App\Services\AI\DTOs\AIUsage;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;
use App\Services\AI\DTOs\EmbeddingResponse;
use App\Services\AI\DTOs\TranscriptionResponse;
use App\Services\AI\Exceptions\AIProviderException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for AIManager (Strategy Pattern dispatcher).
 *
 * All provider calls are mocked — no HTTP requests made.
 * Tests verify:
 *   - Correct provider selection (default vs named)
 *   - Delegation to chat / embed / transcribe
 *   - Error propagation
 *   - Provider registration and resolution
 *   - Health check aggregation
 */
class AIManagerTest extends TestCase
{
    private MockInterface $claudeMock;
    private MockInterface $openAIMock;
    private MockInterface $embeddingMock;
    private MockInterface $transcriptionMock;
    private AIManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->claudeMock        = Mockery::mock(AIProviderInterface::class);
        $this->openAIMock        = Mockery::mock(AIProviderInterface::class);
        $this->embeddingMock     = Mockery::mock(EmbeddingProviderInterface::class);
        $this->transcriptionMock = Mockery::mock(TranscriptionProviderInterface::class);

        // Configure provider name returns for health check tests
        $this->claudeMock->allows('getProviderName')->andReturn('claude')->byDefault();
        $this->openAIMock->allows('getProviderName')->andReturn('openai')->byDefault();
        $this->embeddingMock->allows('getEmbeddingModel')->andReturn('text-embedding-3-small')->byDefault();
        $this->embeddingMock->allows('getDimensions')->andReturn(1536)->byDefault();

        $this->manager = new AIManager(
            embeddingProvider:     $this->embeddingMock,
            transcriptionProvider: $this->transcriptionMock,
            defaultChatProvider:   'claude',
        );

        $this->manager->registerChatProvider('claude', $this->claudeMock);
        $this->manager->registerChatProvider('openai', $this->openAIMock);
    }

    // ── chat() ────────────────────────────────────────────────────────────

    /** @test */
    public function it_routes_chat_to_the_default_claude_provider(): void
    {
        $expected = $this->makeChatResponse('Hello', 'claude');

        $this->claudeMock
            ->expects('chat')
            ->once()
            ->andReturn($expected);

        $this->openAIMock->expects('chat')->never();

        $result = $this->manager->chat([ChatMessage::user('Hello')]);

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_routes_chat_to_named_openai_provider_when_specified(): void
    {
        $expected = $this->makeChatResponse('Hi from OpenAI', 'openai');

        $this->openAIMock
            ->expects('chat')
            ->once()
            ->andReturn($expected);

        $this->claudeMock->expects('chat')->never();

        $result = $this->manager->chat([ChatMessage::user('Hi')], [], provider: 'openai');

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_throws_when_named_provider_is_not_registered(): void
    {
        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessageMatches('/nonexistent/');

        $this->manager->chat([ChatMessage::user('test')], [], provider: 'nonexistent');
    }

    /** @test */
    public function it_passes_options_through_to_the_provider(): void
    {
        $options = ['temperature' => 0.3, 'max_tokens' => 512, 'operation' => 'summarize:bullet'];

        $this->claudeMock
            ->expects('chat')
            ->with(Mockery::any(), $options)
            ->once()
            ->andReturn($this->makeChatResponse('ok', 'claude'));

        $this->manager->chat([ChatMessage::user('test')], $options);
    }

    // ── complete() ────────────────────────────────────────────────────────

    /** @test */
    public function complete_builds_messages_and_delegates_to_chat(): void
    {
        $this->claudeMock
            ->expects('chat')
            ->once()
            ->withArgs(function (array $messages) {
                $this->assertCount(2, $messages);
                $this->assertSame('system', $messages[0]->role);
                $this->assertSame('Be concise.', $messages[0]->content);
                $this->assertSame('user', $messages[1]->role);
                $this->assertSame('What is AI?', $messages[1]->content);
                return true;
            })
            ->andReturn($this->makeChatResponse('AI is...', 'claude'));

        $this->manager->complete('What is AI?', systemPrompt: 'Be concise.');
    }

    /** @test */
    public function complete_without_system_prompt_sends_only_user_message(): void
    {
        $this->claudeMock
            ->expects('chat')
            ->once()
            ->withArgs(function (array $messages) {
                $this->assertCount(1, $messages);
                $this->assertSame('user', $messages[0]->role);
                return true;
            })
            ->andReturn($this->makeChatResponse('ok', 'claude'));

        $this->manager->complete('Hello');
    }

    // ── embed() ───────────────────────────────────────────────────────────

    /** @test */
    public function it_delegates_embed_to_the_embedding_provider(): void
    {
        $expected = $this->makeEmbeddingResponse('test text');

        $this->embeddingMock
            ->expects('embed')
            ->with('test text', [])
            ->once()
            ->andReturn($expected);

        $result = $this->manager->embed('test text');

        $this->assertSame($expected, $result);
    }

    /** @test */
    public function it_delegates_embed_batch_to_the_embedding_provider(): void
    {
        $texts    = ['one', 'two', 'three'];
        $expected = array_map(fn ($t) => $this->makeEmbeddingResponse($t), $texts);

        $this->embeddingMock
            ->expects('embedBatch')
            ->with($texts, [])
            ->once()
            ->andReturn($expected);

        $result = $this->manager->embedBatch($texts);

        $this->assertCount(3, $result);
        $this->assertSame($expected, $result);
    }

    // ── transcribe() ─────────────────────────────────────────────────────

    /** @test */
    public function it_delegates_transcribe_to_the_transcription_provider(): void
    {
        $expected = new TranscriptionResponse(
            text:     'สวัสดีครับ',
            language: 'th',
            model:    'whisper-1',
        );

        $this->transcriptionMock
            ->expects('transcribe')
            ->with('/tmp/audio.mp3', 'th', [])
            ->once()
            ->andReturn($expected);

        $result = $this->manager->transcribe('/tmp/audio.mp3', 'th');

        $this->assertSame($expected, $result);
    }

    // ── healthCheck() ────────────────────────────────────────────────────

    /** @test */
    public function health_check_pings_all_registered_chat_providers(): void
    {
        $this->claudeMock->expects('ping')->once()->andReturn(true);
        $this->openAIMock->expects('ping')->once()->andReturn(true);

        $results = $this->manager->healthCheck();

        $this->assertArrayHasKey('chat:claude', $results);
        $this->assertArrayHasKey('chat:openai', $results);
        $this->assertTrue($results['chat:claude']);
        $this->assertTrue($results['chat:openai']);
    }

    /** @test */
    public function health_check_reports_false_for_unreachable_provider(): void
    {
        $this->claudeMock->allows('ping')->andReturn(false);
        $this->openAIMock->allows('ping')->andReturn(true);

        $results = $this->manager->healthCheck();

        $this->assertFalse($results['chat:claude']);
        $this->assertTrue($results['chat:openai']);
    }

    // ── Provider registration ─────────────────────────────────────────────

    /** @test */
    public function it_returns_the_default_chat_provider_name(): void
    {
        $this->assertSame('claude', $this->manager->getDefaultChatProvider());
    }

    /** @test */
    public function it_returns_embedding_model_from_provider(): void
    {
        $this->assertSame('text-embedding-3-small', $this->manager->getEmbeddingModel());
    }

    /** @test */
    public function it_returns_embedding_dimensions_from_provider(): void
    {
        $this->assertSame(1536, $this->manager->getEmbeddingDimensions());
    }

    /** @test */
    public function register_chat_provider_allows_chaining(): void
    {
        $newProvider = Mockery::mock(AIProviderInterface::class);
        $newProvider->allows('getProviderName')->andReturn('custom');

        $result = $this->manager->registerChatProvider('custom', $newProvider);

        $this->assertSame($this->manager, $result); // fluent interface
    }

    /** @test */
    public function it_can_override_a_registered_provider(): void
    {
        $replacement = Mockery::mock(AIProviderInterface::class);
        $replacement->allows('getProviderName')->andReturn('claude');

        $expected = $this->makeChatResponse('from replacement', 'claude');
        $replacement->expects('chat')->once()->andReturn($expected);
        $this->claudeMock->expects('chat')->never();

        $this->manager->registerChatProvider('claude', $replacement);

        $result = $this->manager->chat([ChatMessage::user('test')]);

        $this->assertSame($expected, $result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeChatResponse(string $content, string $provider): ChatResponse
    {
        return new ChatResponse(
            content:    $content,
            provider:   $provider,
            model:      $provider === 'claude' ? 'claude-sonnet-4-5' : 'gpt-4o-mini',
            usage:      AIUsage::zero(),
            stopReason: 'end_turn',
        );
    }

    private function makeEmbeddingResponse(string $text): EmbeddingResponse
    {
        return new EmbeddingResponse(
            vector:     array_fill(0, 1536, 0.001),
            inputText:  $text,
            model:      'text-embedding-3-small',
            tokenCount: 5,
            dimensions: 1536,
        );
    }
}
