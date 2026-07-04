<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transcribes audio/video files using OpenAI Whisper API.
 *
 * Required config:
 *   services.openai.key   — OPENAI_API_KEY
 *   services.openai.whisper_model — default 'whisper-1'
 */
class TranscriptionService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    // Whisper supports these formats
    private const SUPPORTED_FORMATS = ['mp3', 'mp4', 'mpeg', 'mpga', 'm4a', 'wav', 'webm'];

    // Max file size: 25 MB per Whisper API limit
    private const MAX_SIZE_BYTES = 25 * 1024 * 1024;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
        $this->model  = config('services.openai.whisper_model', 'whisper-1');
        $this->apiUrl = 'https://api.openai.com/v1/audio/transcriptions';
    }

    /**
     * Transcribe a local audio/video file.
     *
     * @param  string      $filePath    Local path to the audio/video file
     * @param  string|null $language    ISO-639-1 hint (e.g. 'th'). null = auto-detect
     * @return array{
     *   text: string,
     *   language: string,
     *   duration: float,
     *   segments: array,
     *   model: string
     * }
     */
    public function transcribe(string $filePath, ?string $language = null): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Audio file not found: {$filePath}");
        }

        $fileSize = filesize($filePath);
        if ($fileSize > self::MAX_SIZE_BYTES) {
            throw new RuntimeException(
                'Audio file too large (' . round($fileSize / 1024 / 1024, 1) . ' MB). '
                . 'Max is 25 MB. Consider splitting the file.'
            );
        }

        $params = [
            'model'           => $this->model,
            'response_format' => 'verbose_json',
            'timestamp_granularities' => ['segment'],
        ];
        if ($language) {
            $params['language'] = $language;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->attach(
            'file',
            file_get_contents($filePath),
            basename($filePath),
        )->post($this->apiUrl, $params);

        if (!$response->successful()) {
            Log::error('Whisper API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException(
                'Whisper API failed (' . $response->status() . '): ' . $response->body()
            );
        }

        $data = $response->json();

        return [
            'text'     => $data['text'] ?? '',
            'language' => $data['language'] ?? ($language ?? 'unknown'),
            'duration' => $data['duration'] ?? 0.0,
            'segments' => $data['segments'] ?? [],
            'model'    => $this->model,
        ];
    }
}
