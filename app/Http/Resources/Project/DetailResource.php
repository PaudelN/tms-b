<?php

namespace App\Http\Resources\Project;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailResource extends JsonResource
{
    /**
     * Full shape for the project detail / settings page.
     * Eager-loaded in the controller via:
     *   $project->load('creator', 'workspace')
     *   $project->loadCount('tasks')
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'cover_image' => $this->cover_image,

            'status' => [
                'value'       => $this->status->value,
                'label'       => $this->status->label(),
                'description' => $this->status->description(),
                'color'       => $this->status->color(),
                'dot'         => $this->status->dotClass(),
                'badge'       => $this->status->badgeClass(),
            ],

            'visibility' => [
                'value'       => $this->visibility->value,
                'label'       => $this->visibility->label(),
                'description' => $this->visibility->description(),
            ],

            // Workspace summary — always loaded for context
            'workspace' => $this->whenLoaded('workspace', fn () => [
                'id'   => $this->workspace->id,
                'name' => $this->workspace->name,
                'slug' => $this->workspace->slug,
            ]),

            // Creator — always loaded
            'creator' => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            // Counts — only present when withCount() was called
            'tasks_count'   => $this->whenCounted('tasks'),
            'members_count' => $this->whenCounted('members'),

            // Computed state flags — frontend uses these to show/hide actions
            'is_draft'       => $this->isDraft(),
            'is_in_progress' => $this->isInProgress(),
            'is_on_hold'     => $this->isOnHold(),
            'is_cancelled'   => $this->isCancelled(),
            'is_completed'   => $this->isCompleted(),

            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date'   => $this->end_date?->format('Y-m-d'),
            'extra'      => $this->extra,

            'created_at' => $this->created_at->format('M d, Y \a\t h:i A'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
