<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $sourceType = $this->faker->randomElement([
            Document::SOURCE_PDF,
            Document::SOURCE_DOCX,
            Document::SOURCE_TXT,
            Document::SOURCE_IMAGE,
        ]);

        return [
            'user_id'        => User::factory(),
            'title'          => $this->faker->sentence(4),
            'description'    => $this->faker->optional()->paragraph(),
            'source_type'    => $sourceType,
            'file_name'      => $this->faker->word() . '.' . $sourceType,
            'file_path'      => 'documents/1/' . $this->faker->uuid() . '.' . $sourceType,
            'mime_type'      => $this->mimeFor($sourceType),
            'file_size'      => $this->faker->numberBetween(1024, 1024 * 1024),
            'source_url'     => null,
            'status'         => Document::STATUS_COMPLETED,
            'extracted_text' => $this->faker->paragraphs(3, true),
            'language'       => 'th',
            'page_count'     => $this->faker->numberBetween(1, 50),
            'visibility'     => Document::VISIBILITY_PRIVATE,
            'is_active'      => true,
        ];
    }

    // ─── States ───────────────────────────────────────────────────

    public function pending(): static
    {
        return $this->state(['status' => Document::STATUS_PENDING]);
    }

    public function processing(): static
    {
        return $this->state(['status' => Document::STATUS_PROCESSING]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Document::STATUS_FAILED]);
    }

    public function public(): static
    {
        return $this->state(['visibility' => Document::VISIBILITY_PUBLIC]);
    }

    public function shared(): static
    {
        return $this->state(['visibility' => Document::VISIBILITY_SHARED]);
    }

    public function audio(): static
    {
        return $this->state([
            'source_type'      => Document::SOURCE_AUDIO,
            'file_name'        => 'recording.mp3',
            'mime_type'        => 'audio/mpeg',
            'duration_seconds' => $this->faker->numberBetween(60, 3600),
            'page_count'       => null,
        ]);
    }

    public function youtube(): static
    {
        return $this->state([
            'source_type' => Document::SOURCE_YOUTUBE,
            'file_path'   => null,
            'file_name'   => null,
            'mime_type'   => null,
            'file_size'   => null,
            'source_url'  => 'https://www.youtube.com/watch?v=' . $this->faker->regexify('[A-Za-z0-9_-]{11}'),
        ]);
    }

    // ─── Private ──────────────────────────────────────────────────

    private function mimeFor(string $sourceType): string
    {
        return match ($sourceType) {
            Document::SOURCE_PDF   => 'application/pdf',
            Document::SOURCE_DOCX  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            Document::SOURCE_TXT   => 'text/plain',
            Document::SOURCE_IMAGE => 'image/png',
            Document::SOURCE_AUDIO => 'audio/mpeg',
            Document::SOURCE_VIDEO => 'video/mp4',
            default                => 'application/octet-stream',
        };
    }
}
