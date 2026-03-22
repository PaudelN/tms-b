<?php

namespace App\Http\Resources\PipelineStage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape for the stages list within a pipeline.
 * Always ordered by display_order — controller applies ->ordered() scope.
 *
 * Eager-loads expected:
 *   ->with('creator')
 */
class ListResource extends JsonResource
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

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'dot'   => $this->status->dotClass(),
                'badge' => $this->status->badgeClass(),
            ],

            // 'tasks_count' => $this->whenCounted('tasks'),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'is_active'   => $this->isActive(),
            'is_inactive' => $this->isInactive(),

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->diffForHumans() : null,
        ];
    }
}
