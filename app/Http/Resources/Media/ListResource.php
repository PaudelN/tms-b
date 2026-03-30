<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alt' => $this->alt,
            'original_filename' => $this->original_filename, 
            'filename' => $this->filename,
            'extension' => $this->extension,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'aggregate_type' => $this->aggregate_type,
            'size' => $this->size,
            'human_size' => $this->human_size,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
