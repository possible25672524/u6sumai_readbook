<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;

/**
 * High-level summarisation service.
 *
 * Supports 7 summary formats as defined in project_memory.md (Module 5).
 * All calls route through AIManager → ClaudeProvider.
 *
 * Usage in a Queue Job:
 *   app(SummarizationService::class)->summarize($text, 'bullet', userId: $job->userId);
 */
final class SummarizationService
{
    /**
     * All 7 summary formats and their instruction fragments.
     */
    private const FORMATS = [
        'short'    => 'Write a concise 2–3 sentence summary capturing the core idea.',
        'detailed' => 'Write a comprehensive multi-paragraph summary covering all major points.',
        'bullet'   => 'Write a structured bullet-point summary (5–10 bullets, hierarchical where appropriate).',
        'exam'     => 'Identify and list the most important facts, definitions, and concepts likely to appear in an exam.',
        'mindmap'  => 'Output a text-based mind-map in indented outline form, starting with the central topic.',
        'table'    => 'Extract key information and present it as a Markdown table (columns: Topic | Key Points | Details).',
        'keypoints'=> 'List the 5 most critical key points a student must remember from this content.',
    ];

    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Summarise text content in a specified format.
     *
     * @param  string  $content   The text to summarise (document chunk or full document)
     * @param  string  $format    One of: short | detailed | bullet | exam | mindmap | table | keypoints
     * @param  string  $language  Output language hint ('th' | 'en' | 'auto')
     * @param  int|null $userId   For usage tracking
     * @return ChatResponse
     *
     * @throws \InvalidArgumentException  For unsupported format
     */
    public function summarize(
        string $content,
        string $format = 'bullet',
        string $language = 'auto',
        ?int $userId = null,
    ): ChatResponse {
        if (! isset(self::FORMATS[$format])) {
            throw new \InvalidArgumentException(
                "Unsupported summary format '{$format}'. Valid: " . implode(', ', array_keys(self::FORMATS))
            );
        }

        $languageInstruction = match ($language) {
            'th'    => 'Respond in Thai.',
            'en'    => 'Respond in English.',
            default => 'Respond in the same language as the input text.',
        };

        $formatInstruction = self::FORMATS[$format];

        $messages = [
            ChatMessage::system(
                config('ai.prompts.summarize_system') . "\n{$languageInstruction}"
            ),
            ChatMessage::user(
                "{$formatInstruction}\n\n---\n\n{$content}"
            ),
        ];

        return $this->ai->chat($messages, [
            'operation' => "summarize:{$format}",
            'user_id'   => $userId,
        ]);
    }

    /**
     * Return all available format keys and their descriptions.
     *
     * @return array<string, string>
     */
    public function availableFormats(): array
    {
        return self::FORMATS;
    }
}
