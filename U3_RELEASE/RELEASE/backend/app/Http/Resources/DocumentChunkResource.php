<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentChunkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'chunk_index'    => $this->chunk_index,
            'content'        => $this->content,
            'token_count'    => $this->token_count,
            'page_number'    => $this->page_number,
            'is_embedded'    => $this->is_embedded,
            'ocr_confidence' => $this->ocr_confidence,
            'chroma_id'      => $this->chroma_id,
        ];
    }
}
