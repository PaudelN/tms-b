<?php

namespace App\Http\Resources\Workspace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListResource extends JsonResource
{
    /**
     * Transform the resource into an array for list/table view.
     * Contains only essential fields for display in tables/grids.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->when(
                $this->description,
                fn() => \Str::limit($this->description, 100)
            ),
            'status'      => [
                'value' => $this->status->value,
                'label' => $this->status->label ?? $this->status->name,
            ],
            'is_archived' => $this->isArchived(),
            'user'        => $this->whenLoaded('user', fn() => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'created_at'  => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at'  => $this->updated_at->diffForHumans(),
        ];
    }
}
