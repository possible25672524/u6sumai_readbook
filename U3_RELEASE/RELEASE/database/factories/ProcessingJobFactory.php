<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\ProcessingJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcessingJobFactory extends Factory
{
    protected $model = ProcessingJob::class;

    public function definition(): array
    {
        return [
            'document_id'   => Document::factory(),
            'job_type'      => $this->faker->randomElement([
                ProcessingJob::TYPE_OCR,
                ProcessingJob::TYPE_TRANSCRIBE,
                ProcessingJob::TYPE_EMBED,
            ]),
            'status'        => ProcessingJob::STATUS_COMPLETED,
            'attempts'      => 1,
            'max_attempts'  => 3,
            'progress'      => 100,
            'error_message' => null,
            'error_context' => null,
            'started_at'    => now()->subMinutes(5),
            'completed_at'  => now(),
            'meta'          => ['chunk_count' => $this->faker->numberBetween(5, 100)],
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status'       => ProcessingJob::STATUS_PENDING,
            'progress'     => 0,
            'started_at'   => null,
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => ProcessingJob::STATUS_FAILED,
            'error_message' => $this->faker->sentence(),
            'attempts'      => 3,
            'completed_at'  => now(),
        ]);
    }

    public function ocr(): static
    {
        return $this->state(['job_type' => ProcessingJob::TYPE_OCR]);
    }

    public function embed(): static
    {
        return $this->state(['job_type' => ProcessingJob::TYPE_EMBED]);
    }
}
