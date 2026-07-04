<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles all file storage operations with MinIO via the 's3' disk.
 * The s3 disk should be configured in config/filesystems.php
 * pointing to MinIO (endpoint, bucket, key, secret).
 */
class DocumentStorageService
{
    private string $disk;
    private string $baseFolder;

    public function __construct()
    {
        $this->disk       = config('filesystems.default_document_disk', 's3');
        $this->baseFolder = 'documents';
    }

    /**
     * Store an uploaded file to MinIO.
     *
     * @param  UploadedFile $file
     * @param  int          $userId
     * @return array{path: string, name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, int $userId): array
    {
        $extension = $file->getClientOriginalExtension();
        $uuid      = (string) Str::uuid();
        $path      = "{$this->baseFolder}/{$userId}/{$uuid}.{$extension}";

        Storage::disk($this->disk)->put(
            $path,
            file_get_contents($file->getRealPath()),
        );

        return [
            'path'  => $path,
            'name'  => $file->getClientOriginalName(),
            'mime'  => $file->getMimeType(),
            'size'  => $file->getSize(),
        ];
    }

    /**
     * Return a temporary URL (presigned) valid for N minutes.
     */
    public function temporaryUrl(string $path, int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($minutes),
        );
    }

    /**
     * Download file content as a string (for OCR/processing).
     */
    public function get(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }

    /**
     * Download file to a local temp path and return that path.
     */
    public function downloadToTemp(string $path): string
    {
        $content  = $this->get($path);
        $tempPath = sys_get_temp_dir() . '/' . basename($path);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Delete a file from MinIO.
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Check if a file exists in MinIO.
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}
