<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatResponse;

/**
 * AI Question Generation service (Module 7).
 *
 * Generates 5 question types from document content.
 * Output is always JSON so the caller can parse and persist to the question_banks table.
 */
final class QuestionGenerationService
{
    private const TYPES = [
        'multiple_choice' => 'multiple-choice questions (4 options each, one correct answer)',
        'true_false'      => 'true/false questions with brief explanations',
        'short_answer'    => 'short-answer questions (2–3 sentence expected answer)',
        'fill_blank'      => 'fill-in-the-blank sentences (use ___ for the blank)',
        'essay'           => 'essay/long-answer questions with marking criteria',
    ];

    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Generate questions from content.
     *
     * @param  string  $content      Source text (document chunk or excerpt)
     * @param  string  $type         One of the TYPES keys
     * @param  int     $count        Number of questions to generate (1–20)
     * @param  string  $difficulty   'easy' | 'medium' | 'hard'
     * @param  int|null $userId
     * @return ChatResponse  Content is a JSON array of question objects
     */
    public function generate(
        string $content,
        string $type = 'multiple_choice',
        int $count = 5,
        string $difficulty = 'medium',
        ?int $userId = null,
    ): ChatResponse {
        if (! isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(
                "Unsupported question type '{$type}'. Valid: " . implode(', ', array_keys(self::TYPES))
            );
        }

        $count = max(1, min(20, $count));
        $typeDesc = self::TYPES[$type];

        $jsonSchema = $this->getJsonSchema($type);

        $messages = [
            ChatMessage::system(config('ai.prompts.question_gen_system')),
            ChatMessage::user(<<<PROMPT
Generate {$count} {$difficulty}-difficulty {$typeDesc} from the following content.

Return ONLY a valid JSON array with NO markdown code blocks, NO explanation text.
Each item must follow this schema: {$jsonSchema}

---
CONTENT:
{$content}
PROMPT),
        ];

        return $this->ai->chat($messages, [
            'operation'   => "question_gen:{$type}",
            'user_id'     => $userId,
            'temperature' => 0.7,   // slight creativity for question variety
        ]);
    }

    public function availableTypes(): array
    {
        return array_keys(self::TYPES);
    }

    private function getJsonSchema(string $type): string
    {
        return match ($type) {
            'multiple_choice' => '{"question": string, "options": [A,B,C,D], "correct": "A"|"B"|"C"|"D", "explanation": string, "page_ref": string|null}',
            'true_false'      => '{"question": string, "answer": true|false, "explanation": string, "page_ref": string|null}',
            'short_answer'    => '{"question": string, "sample_answer": string, "keywords": [string], "page_ref": string|null}',
            'fill_blank'      => '{"sentence": string, "answer": string, "context": string, "page_ref": string|null}',
            'essay'           => '{"question": string, "marking_criteria": [string], "suggested_points": [string], "page_ref": string|null}',
            default           => '{}',
        };
    }
}
