<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Services\AI\DTOs\TranscriptionResponse;

/**
 * Contract for audio-to-text transcription providers.
 *
 * Implementations: WhisperProvider
 * Used by: TranscriptionService (called from Queue Jobs)
 */
interface TranscriptionProviderInterface
{
    /**
     * Transcribe an audio file from a local filesystem path.
     *
     * Supported formats: mp3, mp4, mpeg, mpga, m4a, wav, webm
     * Max file size: 25 MB (Whisper API limit)
     *
     * @param  string  $filePath     Absolute path to the audio file
     * @param  string  $language     BCP-47 language code ('th' for Thai, 'en' for English)
     * @param  array<string, mixed>  $options  Additional provider options (prompt, temperature, etc.)
     * @return TranscriptionResponse
     *
     * @throws \App\Services\AI\Exceptions\AIProviderException
     * @throws \InvalidArgumentException  When the file doesn't exist or exceeds size limit
     */
    public function transcribe(
        string $filePath,
        string $language = 'th',
        array $options = []
    ): TranscriptionResponse;

    /**
     * Return the transcription model name.
     */
    public function getTranscriptionModel(): string;
}
