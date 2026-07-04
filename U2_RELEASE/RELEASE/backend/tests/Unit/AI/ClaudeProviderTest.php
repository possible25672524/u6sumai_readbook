<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Providers\ClaudeProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for ClaudeProvider.
 *
 * Uses Http::fake() to intercept outbound Anthropic API calls.
 * No real API calls are made in this test suite.
 */
class ClaudeProviderTest extends TestCase
{
    private ClaudeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ClaudeProvider(
            apiKey:         'test-api-key',
            model:          'claude-sonnet-4-5',
            maxTokens:      1024,
            timeoutSeconds: 30,
            maxRetries:     2,
        );
    }

    // ── chat() ────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_a_chat_response_on_success(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                $this->anthropicSuccessPayload('Hello from Claude!'),
                200
            ),
        ]);

        $messages = [
            ChatMessage::user('Say hello.'),
        ];

        $response = $this->provider->chat($messages);

        $this->assertInstanceOf(ChatResponse::class, $response);
        $this->assertSame('Hello from Claude!', $response->content);
        $this->assertSame('claude', $response->provider);
        $this->assertSame('claude-sonnet-4-5', $response->model);
        $this->assertSame('end_turn', $response->stopReason);
        $this->assertTrue($response->isComplete());
    }

    /** @test */
    public function it_sends_system_prompt_as_top_level_parameter(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                $this->anthropicSuccessPayload('Acknowledged.'),
                200
            ),
        ]);

        $messages = [
            ChatMessage::system('You are a study assistant.'),
            ChatMessage::user('Help me study.'),
        ];

        $this->provider->chat($messages);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            // System message must be a top-level 'system' key, not inside messages[]
            $this->assertArrayHasKey('system', $body);
            $this->assertSame('You are a study assistant.', $body['system']);
            // messages[] must only contain user/assistant roles
            foreach ($body['messages'] as $msg) {
                $this->assertNotSame('system', $msg['role']);
            }
            return true;
        });
    }

    /** @test */
    public function it_passes_temperature_and_max_tokens_options(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                $this->anthropicSuccessPayload('OK'),
                200
            ),
        ]);

        $this->provider->chat(
            [ChatMessage::user('Test')],
            ['temperature' => 0.5, 'max_tokens' => 512]
        );

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $this->assertSame(0.5, $body['temperature']);
            $this->assertSame(512, $body['max_tokens']);
            return true;
        });
    }

    /** @test */
    public function it_sends_correct_auth_headers(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                $this->anthropicSuccessPayload('OK'),
                200
            ),
        ]);

        $this->provider->chat([ChatMessage::user('Test')]);

        Http::assertSent(function (Request $request) {
            $this->assertSame('test-api-key', $request->header('x-api-key')[0]);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);
            return true;
        });
    }

    /** @test */
    public function it_tracks_token_usage_in_response(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response(
                $this->anthropicSuccessPayload('Response', inputTokens: 50, outputTokens: 30),
                200
            ),
        ]);

        $response = $this->provider->chat([ChatMessage::user('Test')]);

        $this->assertSame(50, $response->usage->promptTokens);
        $this->assertSame(30, $response->usage->completionTokens);
        $this->assertSame(80, $response->usage->totalTokens);
    }

    // ── Error handling ────────────────────────────────────────────────────

    /** @test */
    public function it_throws_rate_limit_exception_on_429(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push($this->anthropicErrorPayload('Rate limit exceeded'), 429, ['retry-after' => '30'])
                ->push($this->anthropicErrorPayload('Rate limit exceeded'), 429, ['retry-after' => '30']),
        ]);

        $this->expectException(AIRateLimitException::class);

        $this->provider->chat([ChatMessage::user('Test')]);
    }

    /** @test */
    public function it_includes_retry_after_seconds_in_rate_limit_exception(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->anthropicErrorPayload('Rate limited'), 429, ['retry-after' => '45'])
                ->push($this->anthropicErrorPayload('Rate limited'), 429, ['retry-after' => '45']),
        ]);

        try {
            $this->provider->chat([ChatMessage::user('Test')]);
            $this->fail('Expected AIRateLimitException');
        } catch (AIRateLimitException $e) {
            $this->assertSame(45, $e->retryAfterSeconds);
            $this->assertSame('claude', $e->provider);
        }
    }

    /** @test */
    public function it_throws_provider_exception_on_500(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                $this->anthropicErrorPayload('Internal server error'),
                500
            ),
        ]);

        $this->expectException(AIProviderException::class);

        $this->provider->chat([ChatMessage::user('Test')]);
    }

    /** @test */
    public function it_propagates_4xx_client_errors_without_retry(): void
    {
        // 401 Unauthorized should not be retried — bad API key is not a transient error
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                $this->anthropicErrorPayload('Invalid API key'),
                401
            ),
        ]);

        $callCount = 0;
        Http::assertSentCount(0); // Baseline

        try {
            $this->provider->chat([ChatMessage::user('Test')]);
        } catch (AIProviderException $e) {
            $this->assertSame(401, $e->statusCode);
        }

        // Should only have made 1 request (no retries on 4xx)
        Http::assertSentCount(1);
    }

    // ── Provider interface ────────────────────────────────────────────────

    /** @test */
    public function it_returns_correct_provider_name(): void
    {
        $this->assertSame('claude', $this->provider->getProviderName());
    }

    /** @test */
    public function it_returns_configured_default_model(): void
    {
        $this->assertSame('claude-sonnet-4-5', $this->provider->getDefaultModel());
    }

    /** @test */
    public function ping_returns_true_on_successful_connection(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(
                $this->anthropicSuccessPayload('pong'),
                200
            ),
        ]);

        $this->assertTrue($this->provider->ping());
    }

    /** @test */
    public function ping_returns_false_on_connection_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
        ]);

        $this->assertFalse($this->provider->ping());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function anthropicSuccessPayload(
        string $text,
        int $inputTokens = 10,
        int $outputTokens = 20,
    ): array {
        return [
            'id'           => 'msg_test123',
            'type'         => 'message',
            'role'         => 'assistant',
            'content'      => [['type' => 'text', 'text' => $text]],
            'model'        => 'claude-sonnet-4-5',
            'stop_reason'  => 'end_turn',
            'usage'        => [
                'input_tokens'  => $inputTokens,
                'output_tokens' => $outputTokens,
            ],
        ];
    }

    private function anthropicErrorPayload(string $message): array
    {
        return [
            'type'  => 'error',
            'error' => ['type' => 'api_error', 'message' => $message],
        ];
    }
}
