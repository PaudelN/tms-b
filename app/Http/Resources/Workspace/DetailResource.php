<?php

namespace App\Http\Resources\Workspace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailResource extends JsonResource
{
    /**
     * Transform the resource into an array for detail view.
     * Contains complete information including relationships.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->name,
            ],
            'extra' => $this->extra,

            // Relationships
            'owner' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],

            // Timestamps with full details
            'created_at' => [
                'formatted' => $this->created_at->format('F d, Y \a\t h:i A'),
                'human' => $this->created_at->diffForHumans(),
                'iso' => $this->created_at->toISOString(),
            ],
            'updated_at' => [
                'formatted' => $this->updated_at->format('F d, Y \a\t h:i A'),
                'human' => $this->updated_at->diffForHumans(),
                'iso' => $this->updated_at->toISOString(),
            ],

            // Additional metadata for detail view
            'is_active' => $this->isActive(),
            'is_archived' => $this->isArchived(),
        ];
    }
}
