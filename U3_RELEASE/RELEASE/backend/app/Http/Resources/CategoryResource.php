<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'description'    => $this->description,
            'parent_id'      => $this->parent_id,
            'parent'         => new CategoryResource($this->whenLoaded('parent')),
            'children'       => CategoryResource::collection($this->whenLoaded('children')),
            'document_count' => $this->when(
                isset($this->documents_count),
                fn() => $this->documents_count
            ),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}
