<?php

namespace App\Http\Resources\Pipeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full shape for the pipeline detail / settings page.
 *
 * Eager-loads expected from the controller:
 *   $pipeline->load('creator', 'project', 'project.workspace')
 *
 * Optional:
 *   $pipeline->loadCount('stages')
 */
class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,

            'status' => [
                'value'       => $this->status->value,
                'label'       => $this->status->label(),
                'description' => $this->status->description(),
                'color'       => $this->status->color(),
                'dot'         => $this->status->dotClass(),
                'badge'       => $this->status->badgeClass(),
            ],

            'extras' => $this->extras,

            // Project summary — always loaded for context
            'project' => $this->whenLoaded('project', fn () => [
                'id'   => $this->project->id,
                'name' => $this->project->name,
                'slug' => $this->project->slug,

                // Workspace nested inside project — loaded via project.workspace
                'workspace' => $this->when(
                    $this->project->relationLoaded('workspace'),
                    fn () => [
                        'id'   => $this->project->workspace->id,
                        'name' => $this->project->workspace->name,
                        'slug' => $this->project->workspace->slug,
                    ]
                ),
            ]),

            // Creator — always loaded
            'creator' => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            // Counts — only present when withCount() was called
            // 'stages_count' => $this->whenCounted('stages'),

            // Computed state flags — frontend uses to show/hide actions
            'is_active'   => $this->isActive(),
            'is_inactive' => $this->isInactive(),

            'created_at' => $this->created_at->format('M d, Y \a\t h:i A'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
