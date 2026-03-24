<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailResource extends JsonResource
{
    /**
     * Full shape for single media detail.
     *
     * Eager-loads expected from the controller:
     *   $media->load('creator', 'project')
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_name' => $this->original_name,
            'path' => $this->path,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'type' => $this->type,
            'size' => $this->size,
            'human_size' => $this->human_size,
            'disk' => $this->disk,
            'is_image' => $this->isImage(),
            'is_video' => $this->isVideo(),
            'is_document' => $this->isDocument(),
            'extra' => $this->extra,

            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'slug' => $this->project->slug,
            ]),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
