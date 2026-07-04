<?php

namespace Tests\Unit;

use App\Models\ProcessingJob;
use Tests\TestCase;

class ProcessingJobModelTest extends TestCase
{
    public function test_can_retry_when_under_max_attempts(): void
    {
        $job = new ProcessingJob([
            'attempts'     => 1,
            'max_attempts' => 3,
        ]);
        $this->assertTrue($job->canRetry());
    }

    public function test_cannot_retry_when_at_max_attempts(): void
    {
        $job = new ProcessingJob([
            'attempts'     => 3,
            'max_attempts' => 3,
        ]);
        $this->assertFalse($job->canRetry());
    }

    public function test_job_type_constants_defined(): void
    {
        $this->assertSame('ocr',        ProcessingJob::TYPE_OCR);
        $this->assertSame('transcribe', ProcessingJob::TYPE_TRANSCRIBE);
        $this->assertSame('embed',      ProcessingJob::TYPE_EMBED);
        $this->assertSame('summarize',  ProcessingJob::TYPE_SUMMARIZE);
    }

    public function test_status_constants_defined(): void
    {
        $this->assertSame('pending',    ProcessingJob::STATUS_PENDING);
        $this->assertSame('processing', ProcessingJob::STATUS_PROCESSING);
        $this->assertSame('completed',  ProcessingJob::STATUS_COMPLETED);
        $this->assertSame('failed',     ProcessingJob::STATUS_FAILED);
    }
}
