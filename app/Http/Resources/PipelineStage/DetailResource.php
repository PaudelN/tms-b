<?php

namespace App\Http\Resources\PipelineStage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full shape for the stage detail / settings panel.
 *
 * Eager-loads expected from the controller:
 *   $stage->load('creator', 'pipeline', 'pipeline.project', 'pipeline.project.workspace')
 */
class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'display_name'  => $this->display_name,
            'display_label' => $this->displayLabel(),
            'display_order' => $this->display_order,
            'color'         => $this->color,
            'wip_limit'     => $this->wip_limit,
            'has_wip_limit' => $this->hasWipLimit(),

            'status' => [
                'value'       => $this->status->value,
                'label'       => $this->status->label(),
                'description' => $this->status->description(),
                'color'       => $this->status->color(),
                'dot'         => $this->status->dotClass(),
                'badge'       => $this->status->badgeClass(),
            ],

            'extras' => $this->extras,

            // Pipeline summary — always loaded
            'pipeline' => $this->whenLoaded('pipeline', fn () => [
                'id'   => $this->pipeline->id,
                'name' => $this->pipeline->name,
                'slug' => $this->pipeline->slug,

                'project' => $this->when(
                    $this->pipeline->relationLoaded('project'),
                    fn () => [
                        'id'   => $this->pipeline->project->id,
                        'name' => $this->pipeline->project->name,
                        'slug' => $this->pipeline->project->slug,

                        'workspace' => $this->when(
                            $this->pipeline->project->relationLoaded('workspace'),
                            fn () => [
                                'id'   => $this->pipeline->project->workspace->id,
                                'name' => $this->pipeline->project->workspace->name,
                                'slug' => $this->pipeline->project->workspace->slug,
                            ]
                        ),
                    ]
                ),
            ]),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            // 'tasks_count' => $this->whenCounted('tasks'),

            'is_active'   => $this->isActive(),
            'is_inactive' => $this->isInactive(),

            'created_at' => $this->created_at->format('M d, Y \a\t h:i A'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
