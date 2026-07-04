<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Self-referencing sibling resources — all in same namespace, no additional use needed.
// PHP resolves them via the current namespace: App\Http\Resources\UserResource etc.

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description'      => $this->description,
            'source_type'      => $this->source_type,
            'file_name'        => $this->file_name,
            'file_size'        => $this->file_size,
            'file_size_human'  => $this->file_size ? $this->humanFileSize($this->file_size) : null,
            'mime_type'        => $this->mime_type,
            'source_url'       => $this->source_url,
            'status'           => $this->status,
            'language'         => $this->language,
            'page_count'       => $this->page_count,
            'duration_seconds' => $this->duration_seconds,
            'visibility'       => $this->visibility,
            'is_active'        => $this->is_active,
            'categories'       => CategoryResource::collection($this->whenLoaded('categories')),
            'processing_jobs'  => ProcessingJobResource::collection($this->whenLoaded('processingJobs')),
            'chunk_count'      => $this->when(
                $this->relationLoaded('chunks'),
                fn() => $this->chunks->count()
            ),
            'user'             => new UserResource($this->whenLoaded('user')),
            'created_at'       => $this->created_at->toIso8601String(),
            'updated_at'       => $this->updated_at->toIso8601String(),
        ];
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
