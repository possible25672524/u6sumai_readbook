<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\AIManager;
use App\Services\AI\DTOs\TranscriptionResponse;

/**
 * High-level transcription service — part of Document Processing Pipeline (Module 4).
 *
 * Called exclusively from Queue Jobs. HTTP requests MUST NOT call this directly
 * because Whisper API calls can take 30–300 seconds for long audio.
 *
 * Supported audio formats: mp3, mp4, mpeg, mpga, m4a, wav, webm (max 25 MB).
 */
final class TranscriptionService
{
    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Transcribe an audio or video file to text.
     *
     * @param  string   $filePath  Absolute path to the audio file on disk
     * @param  string   $language  BCP-47 code — 'th' for Thai, 'en' for English
     * @param  string   $prompt    Optional vocabulary hint to improve accuracy
     *                             (e.g. subject-domain terms, speaker names)
     * @param  int|null $userId    For usage/cost tracking
     * @return TranscriptionResponse
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException
     * @throws \InvalidArgumentException  For unsupported formats or oversized files
     */
    public function transcribe(
        string $filePath,
        string $language = 'th',
        string $prompt = '',
        ?int $userId = null,
    ): TranscriptionResponse {
        $options = [
            'response_format' => 'verbose_json',   // includes timestamps + segments
            'user_id'         => $userId,
        ];

        if ($prompt !== '') {
            $options['prompt'] = $prompt;
        }

        return $this->ai->transcribe($filePath, $language, $options);
    }

    /**
     * Transcribe and return only the plain text (no timestamps).
     * Lighter option when segment data is not needed.
     */
    public function transcribeToText(
        string $filePath,
        string $language = 'th',
        ?int $userId = null,
    ): string {
        $response = $this->transcribe($filePath, $language, '', $userId);

        return $response->text;
    }
}
