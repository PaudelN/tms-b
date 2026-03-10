<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    /**
     * Lightweight shape for table / list / sidebar views.
     * Only what the frontend needs to render a project row or card.
     * Heavy relations (pipelines, tasks) are never included here.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when(
                $this->description,
                fn () => \Str::limit($this->description, 100)
            ),
            'cover_image' => $this->cover_image,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'dot' => $this->status->dotClass(),
                'badge' => $this->status->badgeClass(),
            ],

            'visibility' => [
                'value' => $this->visibility->value,
                'label' => $this->visibility->label(),
            ],

            // Counts — only present when withCount() was called on the query.
            // Frontend uses these for the task count badge in the sidebar.
            'tasks_count' => $this->whenCounted('tasks'),
            'members_count' => $this->whenCounted('members'),

            // Creator — only present when load('creator') was called
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->diffForHumans(),        ];
    }
}
