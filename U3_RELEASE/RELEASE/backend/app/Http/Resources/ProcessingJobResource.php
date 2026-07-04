<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessingJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'job_type'      => $this->job_type,
            'status'        => $this->status,
            'progress'      => $this->progress,
            'attempts'      => $this->attempts,
            'max_attempts'  => $this->max_attempts,
            'error_message' => $this->when(
                $this->status === 'failed',
                $this->error_message
            ),
            'meta'          => $this->meta,
            'started_at'    => $this->started_at?->toIso8601String(),
            'completed_at'  => $this->completed_at?->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
