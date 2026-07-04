<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'document_id'      => $this->document_id,
            'content'          => $this->content,
            'language'         => $this->language,
            'duration_seconds' => $this->duration_seconds,
            'avg_logprob'      => $this->avg_logprob,
            'segments'         => $this->when(
                (bool) request()->query('with_segments'),
                $this->segments
            ),
            'provider'         => $this->provider,
            'model'            => $this->model,
            'created_at'       => $this->created_at->toIso8601String(),
        ];
    }
}
