<?php

namespace App\Http\Resources\Activity;

use App\Enums\ActivityEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ActivityResource
 *
 * The description is built by delegating to ActivityEvent::description().
 * This resource contains zero match/switch statements — all event-specific
 * logic lives in the enum. Adding a new event type only requires changes
 * to ActivityEvent, not here.
 */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event      = ActivityEvent::tryFrom($this->event);
        $causerName = $this->causer?->name ?? 'System';
        $props      = $this->properties ?? [];

        return [
            'id'       => $this->id,

            // Raw event string — for frontend icon/badge mapping
            'event'    => $this->event,

            // Enum category — for frontend tab grouping (lifecycle, kanban_card, etc.)
            'category' => $event?->category() ?? 'other',

            // Short label — for badges ("Moved", "Updated")
            'label'    => $event?->label() ?? $this->event,

            // Full sentence — for timeline rendering
            'description' => $event
                ? $event->description($causerName, $props)
                : "{$causerName} performed {$this->event}",

            // Causer
            'causer' => [
                'id'     => $this->causer_id,
                'name'   => $causerName,
                'avatar' => null, // extend when User has avatar
            ],

            // Full properties blob — frontend uses this for rich diff rendering
            // e.g. showing old → new values inline in the timeline
            'properties' => $props,

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'time_ago'   => $this->created_at->diffForHumans(),
            'date_label' => $this->created_at->toDateString(), // for date-group headers
        ];
    }
}
