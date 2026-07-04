<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\TranscriptionProviderInterface;
use App\Services\AI\Concerns\HasRetry;
use App\Services\AI\Concerns\TracksUsage;
use App\Services\AI\DTOs\AIUsage;
use App\Services\AI\DTOs\TranscriptionResponse;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Whisper transcription provider.
 *
 * Called from Queue Jobs (never in HTTP request scope) because
 * transcription of long audio files can take tens of seconds.
 *
 * Pricing: ~$0.006 / minute of audio (June 2025).
 *
 * @see https://platform.openai.com/docs/api-reference/audio/createTranscription
 */
final class WhisperProvider implements TranscriptionProviderInterface
{
    use HasRetry, TracksUsage;

    private const API_BASE       = 'https://api.openai.com';
    private const PROVIDER       = 'openai';
    private const MAX_FILE_BYTES = 25 * 1024 * 1024;   // 25 MB Whisper limit
    private const SUPPORTED_EXTS = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int    $timeoutSeconds,
        private readonly int    $maxRetries,
    ) {}

    // ── TranscriptionProviderInterface ────────────────────────────────────

    public function transcribe(
        string $filePath,
        string $language = 'th',
        array $options = [],
    ): TranscriptionResponse {
        $this->validateFile($filePath);

        $model        = $options['model'] ?? $this->model;
        $responseFormat = $options['response_format'] ?? 'verbose_json';

        $payload = [
            'model'           => $model,
            'language'        => $language,
            'response_format' => $responseFormat,
        ];

        // Optional Whisper prompt to improve accuracy (e.g. domain-specific vocabulary)
        if (isset($options['prompt'])) {
            $payload['prompt'] = $options['prompt'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $rawResponse = $this->withRetry(
            fn () => $this->makeMultipartRequest('/v1/audio/transcriptions', $filePath, $payload),
            context: "openai.whisper.{$model}",
            maxAttempts: $this->maxRetries,
        );

        // Whisper doesn't return token usage in the API response;
        // we approximate it from audio duration for cost tracking.
        $durationSeconds = (float) ($rawResponse['duration'] ?? 0);
        $approxMinutes   = $durationSeconds / 60;
        $approxCostUsd   = round($approxMinutes * 0.006, 6);

        $this->recordUsage(
            provider:  self::PROVIDER,
            model:     $model,
            operation: 'transcribe',
            usage:     new AIUsage(
                promptTokens:      0,
                completionTokens:  0,
                totalTokens:       0,
                estimatedCostUsd:  $approxCostUsd,
            ),
            userId: $options['user_id'] ?? null,
        );

        Log::info("[WhisperProvider] transcription completed.", [
            'file'     => basename($filePath),
            'language' => $language,
            'duration' => $durationSeconds,
        ]);

        return new TranscriptionResponse(
            text:            $rawResponse['text'] ?? '',
            language:        $rawResponse['language'] ?? $language,
            model:           $model,
            durationSeconds: $durationSeconds ?: null,
            words:           $rawResponse['words']    ?? [],
            segments:        $rawResponse['segments'] ?? [],
        );
    }

    public function getTranscriptionModel(): string
    {
        return $this->model;
    }

    // ── Internal ───────────────────────────────────────────────────────────

    private function makeMultipartRequest(
        string $path,
        string $filePath,
        array $fields,
    ): array {
        // Build multipart parts in the format Laravel Http client expects.
        // BUG FIX 1: previously used ->attach() for plain text fields (model, language, etc.)
        // which sent them as file attachments with application/octet-stream MIME type.
        // OpenAI Whisper endpoint requires these as plain form fields — fixed via asMultipart().
        //
        // BUG FIX 2: fopen() handle is now closed in a finally block to prevent
        // file descriptor leaks under concurrent queue workers.

        $fileHandle = fopen($filePath, 'r');

        if ($fileHandle === false) {
            throw new AIProviderException(
                "Failed to open audio file for reading: {$filePath}",
                provider: self::PROVIDER,
            );
        }

        // File part — sent as a proper binary file attachment
        $multipart = [[
            'name'     => 'file',
            'contents' => $fileHandle,
            'filename' => basename($filePath),
        ]];

        // Plain text form fields: model, language, response_format, prompt, temperature
        foreach ($fields as $key => $value) {
            $multipart[] = [
                'name'     => $key,
                'contents' => (string) $value,
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeoutSeconds)
                ->asMultipart()
                ->post(self::API_BASE . $path, $multipart);
        } catch (ConnectionException $e) {
            throw new AIProviderException(
                "Connection to Whisper API failed: {$e->getMessage()}",
                provider: self::PROVIDER,
                previous: $e,
            );
        } finally {
            // Always close the handle — even when the request threw
            if (is_resource($fileHandle)) {
                fclose($fileHandle);
            }
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(Response $response): array
    {
        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('retry-after') ?: null;
            throw new AIRateLimitException(self::PROVIDER, $retryAfter);
        }

        if (! $response->successful()) {
            $body    = $response->json();
            $message = $body['error']['message'] ?? $response->body();

            throw new AIProviderException(
                "Whisper API error [{$response->status()}]: {$message}",
                provider:   self::PROVIDER,
                statusCode: $response->status(),
            );
        }

        return $response->json();
    }

    private function validateFile(string $filePath): void
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("Audio file not found: {$filePath}");
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($ext, self::SUPPORTED_EXTS, true)) {
            throw new \InvalidArgumentException(
                "Unsupported audio format '.{$ext}'. Supported: " . implode(', ', self::SUPPORTED_EXTS)
            );
        }

        $size = filesize($filePath);
        if ($size > self::MAX_FILE_BYTES) {
            $sizeMB = round($size / (1024 * 1024), 1);
            throw new \InvalidArgumentException(
                "Audio file ({$sizeMB} MB) exceeds the 25 MB Whisper API limit."
            );
        }
    }
}
