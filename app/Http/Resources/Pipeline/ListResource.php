<?php

namespace App\Http\Resources\Pipeline;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight shape for table / list views.
 * Never includes stages or heavy relations — those live in DetailResource.
 *
 * Eager-loads expected from the controller:
 *   ->with('creator')
 */
class ListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,

            'description' => $this->when(
                $this->description,
                fn () => \Str::limit($this->description, 100)
            ),

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'dot'   => $this->status->dotClass(),
                'badge' => $this->status->badgeClass(),
            ],

            // Counts — only present when withCount() was called on the query
            'stages_count' => $this->whenCounted('stages'),

            // Creator — only present when load('creator') was called
            'creator' => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),

            'created_at' =>$this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->diffForHumans() : null,
        ];
    }
}
