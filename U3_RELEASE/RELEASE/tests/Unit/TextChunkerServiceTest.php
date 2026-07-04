<?php

namespace Tests\Unit;

use App\Services\TextChunkerService;
use Tests\TestCase;

class TextChunkerServiceTest extends TestCase
{
    private TextChunkerService $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new TextChunkerService(maxChars: 200, overlapChars: 40);
    }

    public function test_empty_text_returns_no_chunks(): void
    {
        $chunks = $this->chunker->chunk('');
        $this->assertEmpty($chunks);
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $text   = 'สวัสดีครับ นี่คือข้อความทดสอบสั้นๆ';
        $chunks = $this->chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertSame(0, $chunks[0]['index']);
        $this->assertStringContainsString('สวัสดี', $chunks[0]['content']);
    }

    public function test_long_text_is_split_into_multiple_chunks(): void
    {
        $text   = str_repeat('This is a sentence for testing. ', 30); // ~960 chars
        $chunks = $this->chunker->chunk($text);

        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_chunks_have_sequential_indexes(): void
    {
        $text   = str_repeat('Lorem ipsum dolor sit amet. ', 30);
        $chunks = $this->chunker->chunk($text);

        foreach ($chunks as $i => $chunk) {
            $this->assertSame($i, $chunk['index']);
        }
    }

    public function test_chunks_contain_required_keys(): void
    {
        $chunks = $this->chunker->chunk('Hello world. This is test content.');

        foreach ($chunks as $chunk) {
            $this->assertArrayHasKey('index', $chunk);
            $this->assertArrayHasKey('content', $chunk);
            $this->assertArrayHasKey('char_start', $chunk);
            $this->assertArrayHasKey('char_end', $chunk);
            $this->assertArrayHasKey('token_count', $chunk);
        }
    }

    public function test_chunk_content_is_non_empty(): void
    {
        $text   = "Paragraph one.\n\nParagraph two.\n\nParagraph three.";
        $chunks = $this->chunker->chunk($text);

        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk['content']));
        }
    }

    public function test_token_count_is_positive(): void
    {
        $text   = 'Testing token count estimation for chunker service.';
        $chunks = $this->chunker->chunk($text);

        foreach ($chunks as $chunk) {
            $this->assertGreaterThan(0, $chunk['token_count']);
        }
    }

    public function test_char_start_end_are_consistent(): void
    {
        $text   = str_repeat('A sentence here. ', 20);
        $chunks = $this->chunker->chunk($text);

        foreach ($chunks as $chunk) {
            $this->assertLessThan($chunk['char_end'], $chunk['char_start']);
            $len = $chunk['char_end'] - $chunk['char_start'];
            $this->assertLessThanOrEqual(250, $len); // maxChars + small buffer
        }
    }

    public function test_thai_text_is_chunked_correctly(): void
    {
        $thai = str_repeat('นี่คือประโยคทดสอบภาษาไทย ซึ่งใช้สำหรับทดสอบการแบ่งข้อความ ', 10);
        $chunks = $this->chunker->chunk($thai);

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk['content']);
        }
    }
}
