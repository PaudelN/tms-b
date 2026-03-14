<?php

namespace App\Http\Resources\Task;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'task_number' => $this->task_number,
            'title'       => $this->title,
            'description' => $this->when(
                $this->description,
                fn () => \Str::limit($this->description, 120)
            ),

            // Priority — full object so frontend can render badge + dot without enum lookup
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
                'dot'   => $this->priority->dot(),
                'badge' => $this->priority->badge(),
            ],

            // Stage — id is the kanban column key on the frontend (String(stage.id))
            'stage' => $this->whenLoaded('stage', fn () => [
                'id'            => $this->stage->id,
                'name'          => $this->stage->name,
                'display_label' => $this->stage->display_label,
                'color'         => $this->stage->color,
            ]),

            'pipeline_id'       => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'project_id'        => $this->project_id,

            'due_date'   => $this->due_date?->format('Y-m-d'),
            'is_overdue' => $this->isOverdue(),
            'is_due_today' => $this->isDueToday(),

            'creator' => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
