<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'task_number' => $this->task_number,
            'title'       => $this->title,
            'description' => $this->description,

            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
                'dot'   => $this->priority->dot(),
                'badge' => $this->priority->badge(),
            ],

            'stage' => $this->whenLoaded('stage', fn () => [
                'id'            => $this->stage->id,
                'name'          => $this->stage->name,
                'display_label' => $this->stage->display_label,
                'color'         => $this->stage->color,
                'wip_limit'     => $this->stage->wip_limit,
            ]),

            'pipeline' => $this->whenLoaded('pipeline', fn () => [
                'id'   => $this->pipeline->id,
                'name' => $this->pipeline->name,
                'slug' => $this->pipeline->slug,
            ]),

            'project' => $this->whenLoaded('project', fn () => [
                'id'   => $this->project->id,
                'name' => $this->project->name,
                'slug' => $this->project->slug,
            ]),

            'pipeline_id'       => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'project_id'        => $this->project_id,

            'due_date'     => $this->due_date?->format('Y-m-d'),
            'is_overdue'   => $this->isOverdue(),
            'is_due_today' => $this->isDueToday(),
            'extra'        => $this->extra,
            'sort_order'   => $this->sort_order,

            'creator' => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            'updater' => $this->whenLoaded('updater', fn () => [
                'id'   => $this->updater->id,
                'name' => $this->updater->name,
            ]),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
