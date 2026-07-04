<?php

declare(strict_types=1);

namespace App\Services\AI\DTOs;

/**
 * Normalised response from TranscriptionProviderInterface::transcribe().
 */
final class TranscriptionResponse
{
    public function __construct(
        /** Full transcribed text */
        public readonly string $text,
        /** Detected or specified language (BCP-47) */
        public readonly string $language,
        /** Transcription model used */
        public readonly string $model,
        /** Audio duration in seconds (null if provider doesn't return it) */
        public readonly ?float $durationSeconds = null,
        /**
         * Word-level timestamps (when verbose_json format is requested).
         * Each item: ['word' => string, 'start' => float, 'end' => float]
         */
        public readonly array $words = [],
        /**
         * Segment-level timestamps.
         * Each item: ['id', 'start', 'end', 'text', 'tokens', 'no_speech_prob']
         */
        public readonly array $segments = [],
    ) {}

    /**
     * Word count of the transcription.
     */
    public function wordCount(): int
    {
        return str_word_count($this->text);
    }

    /**
     * Whether the transcription contains meaningful content.
     */
    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}
