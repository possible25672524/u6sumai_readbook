<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal user representation embedded in other resources (e.g. DocumentResource).
 * Does NOT expose sensitive fields (password, remember_token, etc.).
 * Full auth responses use the UserResource defined in Phase 1 Auth responses;
 * this resource is for embedding within other resource contexts.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'role' => $this->whenLoaded('role', fn() => [
                'id'   => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
        ];
    }
}
