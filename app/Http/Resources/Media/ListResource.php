<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    /**
     * Lightweight shape for media gallery / list views.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'type' => $this->type,
            'size' => $this->size,
            'human_size' => $this->human_size,
            'is_image' => $this->isImage(),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
