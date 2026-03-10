<?php

namespace App\Http\Resources\Workspace;

use App\Http\Resources\Project\ListResource as ProjectListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'status'      => $this->status?->value,
            'is_active'   => $this->isActive(),
            'is_archived' => $this->isArchived(),
            'extra'       => $this->extra,

            'user' => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ],

            // Projects are only serialized when eager-loaded.
            // WorkspaceController::show() loads them via ->load('user', 'projects.creator').
            // Index/board/kanban routes do NOT load projects — keeping list responses lightweight.
            'projects' => $this->whenLoaded(
                'projects',
                fn () => ProjectListResource::collection($this->projects)
            ),

            'created_at' => $this->created_at->format('M d, Y \a\t h:i A'),
            'updated_at' => $this->updated_at?->diffForHumans(),
        ];
    }
}
