<?php

namespace App\Events;

use App\Http\Resources\Activity\ActivityResource;
use App\Models\Activity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ActivityLogged
 *
 * Broadcasts a single activity to the private channel for its subject.
 *
 * Channel pattern:
 *   private-{morphAlias}.{subjectId}
 *   e.g. private-task.42, private-pipeline.7
 *
 * We use ShouldBroadcastNow (not ShouldBroadcast) so the broadcast
 * goes out synchronously inside the queue worker, right after the
 * Activity row is persisted — no extra queue hop.
 */
class ActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Activity $activity) {}

    public function broadcastOn(): array
    {
        // subject_type is already the morph alias ("task", "pipeline", etc.)
        $channel = "{$this->activity->subject_type}.{$this->activity->subject_id}";

        return [new PrivateChannel($channel)];
    }

    public function broadcastAs(): string
    {
        return 'ActivityLogged';
    }

    /**
     * The payload sent to the frontend.
     * Re-uses ActivityResource so the shape is identical to the REST API.
     */
    public function broadcastWith(): array
    {
        // Load causer relationship so ActivityResource can resolve the name
        $this->activity->loadMissing('causer:id,name');

        return (new ActivityResource($this->activity))->resolve();
    }
}
