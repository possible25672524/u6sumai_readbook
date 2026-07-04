<?php

namespace Tests\Unit;

use App\Models\Document;
use Tests\TestCase;

class DocumentModelTest extends TestCase
{
    public function test_is_file_source_returns_true_for_pdf(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_PDF]);
        $this->assertTrue($doc->isFileSource());
    }

    public function test_is_file_source_returns_false_for_youtube(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_YOUTUBE]);
        $this->assertFalse($doc->isFileSource());
    }

    public function test_is_audio_source_for_audio_type(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_AUDIO]);
        $this->assertTrue($doc->isAudioSource());
    }

    public function test_is_audio_source_for_video_type(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_VIDEO]);
        $this->assertTrue($doc->isAudioSource());
    }

    public function test_is_audio_source_false_for_pdf(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_PDF]);
        $this->assertFalse($doc->isAudioSource());
    }

    public function test_is_url_source_for_youtube(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_YOUTUBE]);
        $this->assertTrue($doc->isUrlSource());
    }

    public function test_needs_ocr_for_image(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_IMAGE]);
        $this->assertTrue($doc->needsOcr());
    }

    public function test_needs_ocr_for_pdf(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_PDF]);
        $this->assertTrue($doc->needsOcr());
    }

    public function test_needs_ocr_false_for_txt(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_TXT]);
        $this->assertFalse($doc->needsOcr());
    }

    public function test_needs_transcription_for_audio(): void
    {
        $doc = new Document(['source_type' => Document::SOURCE_AUDIO]);
        $this->assertTrue($doc->needsTranscription());
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending',    Document::STATUS_PENDING);
        $this->assertSame('processing', Document::STATUS_PROCESSING);
        $this->assertSame('completed',  Document::STATUS_COMPLETED);
        $this->assertSame('failed',     Document::STATUS_FAILED);
    }

    public function test_visibility_constants_are_defined(): void
    {
        $this->assertSame('private', Document::VISIBILITY_PRIVATE);
        $this->assertSame('shared',  Document::VISIBILITY_SHARED);
        $this->assertSame('public',  Document::VISIBILITY_PUBLIC);
    }
}
