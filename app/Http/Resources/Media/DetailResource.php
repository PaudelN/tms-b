<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full shape used for single-media show/store responses.
 *
 * Adds disk / directory / uploader fields on top of ListResource.
 */
class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'disk' => $this->disk,
            'directory' => $this->directory,
            'filename' => $this->filename,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'aggregate_type' => $this->aggregate_type,
            'size' => $this->size,
            'original_filename' => $this->original_filename,
            'alt' => $this->alt,

            // Pivot columns — present only when loaded via morphToMany
            'tag' => $this->whenPivotLoaded('mediables', fn () => $this->pivot->tag),
            'order' => $this->whenPivotLoaded('mediables', fn () => $this->pivot->order),

            'uploaded_by' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
