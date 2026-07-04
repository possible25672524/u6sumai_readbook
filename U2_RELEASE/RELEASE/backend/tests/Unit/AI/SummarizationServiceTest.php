<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\AIUsage;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;
use App\Services\SummarizationService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for SummarizationService.
 *
 * Verifies all 7 formats, language options, system prompt construction,
 * and option pass-through to AIManager.
 *
 * AIManager is mocked — no real AI calls made.
 */
class SummarizationServiceTest extends TestCase
{
    private MockInterface $aiManagerMock;
    private SummarizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aiManagerMock = Mockery::mock(AIManager::class);
        $this->service       = new SummarizationService($this->aiManagerMock);
    }

    // ── Format validation ─────────────────────────────────────────────────

    /** @test */
    public function it_throws_for_unsupported_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported summary format/');
        $this->expectExceptionMessageMatches('/invalid_format/');

        $this->service->summarize('some content', 'invalid_format');
    }

    /** @test */
    public function it_includes_valid_formats_in_the_exception_message(): void
    {
        try {
            $this->service->summarize('content', 'bad_format');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('bullet', $e->getMessage());
            $this->assertStringContainsString('short', $e->getMessage());
            $this->assertStringContainsString('keypoints', $e->getMessage());
        }
    }

    // ── All 7 formats ────────────────────────────────────────────────────

    /**
     * @test
     * @dataProvider allFormatsProvider
     */
    public function it_accepts_all_seven_summary_formats(string $format): void
    {
        $this->aiManagerMock
            ->expects('chat')
            ->once()
            ->andReturn($this->makeChatResponse("Summary in {$format} format"));

        $response = $this->service->summarize('content', $format);

        $this->assertSame("Summary in {$format} format", $response->content);
    }

    public static function allFormatsProvider(): array
    {
        return [
            'short'     => ['short'],
            'detailed'  => ['detailed'],
            'bullet'    => ['bullet'],
            'exam'      => ['exam'],
            'mindmap'   => ['mindmap'],
            'table'     => ['table'],
            'keypoints' => ['keypoints'],
        ];
    }

    // ── System prompt construction ────────────────────────────────────────

    /** @test */
    public function it_sends_system_message_as_first_message(): void
    {
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->once()
            ->withArgs(function (array $messages, array $options) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('test content', 'bullet');

        $this->assertNotNull($capturedMessages);
        $this->assertSame('system', $capturedMessages[0]->role);
        $this->assertSame('user', $capturedMessages[1]->role);
    }

    /** @test */
    public function it_includes_thai_language_instruction_in_system_prompt(): void
    {
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->once()
            ->withArgs(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet', 'th');

        $systemContent = $capturedMessages[0]->content;
        $this->assertStringContainsString('Thai', $systemContent);
    }

    /** @test */
    public function it_includes_english_language_instruction_when_specified(): void
    {
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet', 'en');

        $this->assertStringContainsString('English', $capturedMessages[0]->content);
    }

    /** @test */
    public function it_uses_auto_language_instruction_by_default(): void
    {
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet');  // no language arg

        $this->assertStringContainsString('same language', $capturedMessages[0]->content);
    }

    // ── User message construction ─────────────────────────────────────────

    /** @test */
    public function it_includes_the_content_in_the_user_message(): void
    {
        $content          = 'This is the document text to summarise.';
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('summary'));

        $this->service->summarize($content, 'bullet');

        $userContent = $capturedMessages[1]->content;
        $this->assertStringContainsString($content, $userContent);
    }

    /** @test */
    public function bullet_format_includes_bullet_instruction_in_user_message(): void
    {
        $capturedMessages = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages) use (&$capturedMessages) {
                $capturedMessages = $messages;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet');

        $this->assertStringContainsString('bullet', strtolower($capturedMessages[1]->content));
    }

    // ── Options pass-through ──────────────────────────────────────────────

    /** @test */
    public function it_passes_operation_key_in_options(): void
    {
        $capturedOptions = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'exam');

        $this->assertArrayHasKey('operation', $capturedOptions);
        $this->assertSame('summarize:exam', $capturedOptions['operation']);
    }

    /** @test */
    public function it_passes_user_id_in_options_when_provided(): void
    {
        $capturedOptions = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet', 'th', userId: 42);

        $this->assertSame(42, $capturedOptions['user_id']);
    }

    /** @test */
    public function it_passes_null_user_id_when_not_provided(): void
    {
        $capturedOptions = null;

        $this->aiManagerMock
            ->expects('chat')
            ->withArgs(function (array $messages, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return true;
            })
            ->andReturn($this->makeChatResponse('ok'));

        $this->service->summarize('content', 'bullet');

        $this->assertNull($capturedOptions['user_id']);
    }

    // ── availableFormats() ────────────────────────────────────────────────

    /** @test */
    public function it_returns_all_seven_formats_from_available_formats(): void
    {
        $formats = $this->service->availableFormats();

        $this->assertCount(7, $formats);
        $this->assertArrayHasKey('short', $formats);
        $this->assertArrayHasKey('detailed', $formats);
        $this->assertArrayHasKey('bullet', $formats);
        $this->assertArrayHasKey('exam', $formats);
        $this->assertArrayHasKey('mindmap', $formats);
        $this->assertArrayHasKey('table', $formats);
        $this->assertArrayHasKey('keypoints', $formats);
    }

    /** @test */
    public function available_formats_values_are_non_empty_strings(): void
    {
        foreach ($this->service->availableFormats() as $key => $description) {
            $this->assertIsString($description, "Format '{$key}' description must be a string");
            $this->assertNotEmpty($description, "Format '{$key}' description must not be empty");
        }
    }

    // ── Response passthrough ──────────────────────────────────────────────

    /** @test */
    public function it_returns_the_chat_response_unchanged(): void
    {
        $expected = $this->makeChatResponse('Bullet point summary here');

        $this->aiManagerMock
            ->allows('chat')
            ->andReturn($expected);

        $result = $this->service->summarize('content', 'bullet');

        $this->assertSame($expected, $result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeChatResponse(string $content): ChatResponse
    {
        return new ChatResponse(
            content:    $content,
            provider:   'claude',
            model:      'claude-sonnet-4-5',
            usage:      AIUsage::zero(),
            stopReason: 'end_turn',
        );
    }
}
