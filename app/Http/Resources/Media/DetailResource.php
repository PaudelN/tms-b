<?php

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DetailResource
 *
 * Maps the backend Media model to the canonical frontend shape.
 *
 * Backend field      → Frontend field
 * ──────────────────────────────────────────────────────
 * aggregate_type     → type
 * original_filename  → original_name
 * alt                → alt  (also surfaced as display name)
 * uploaded_by        → uploaded_by
 * uploader (relation)→ uploader { id, name, email }
 * (computed)         → human_size   (from Model accessor)
 *
 * The resource intentionally does NOT include project_id or a project
 * relationship — media is entity-agnostic on the backend. Any consumer
 * that needs to know which entity owns a media item should track that
 * in its own pivot/context, not on the media record itself.
 */
class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // File identity
            'alt' => $this->alt,           // the editable display label
            'original_name' => $this->original_filename, // name as uploaded, no ext
            'filename' => $this->filename,      // uuid slug
            'extension' => $this->extension,

            // Storage
            'disk' => $this->disk,
            'path' => $this->path,          // computed: dir/uuid.ext
            'url' => $this->url,           // computed: full public URL

            // MIME / type
            'mime_type' => $this->mime_type,
            'type' => $this->aggregate_type, // image|video|audio|document|other

            // Size
            'size' => $this->size,          // bytes
            'human_size' => $this->human_size,    // e.g. "1.4 MB"

            // Ownership
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
                'email' => $this->uploader->email,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
