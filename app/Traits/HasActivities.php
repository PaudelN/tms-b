<?php

namespace App\Traits;

use App\Models\Activity;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * HasActivities
 *
 * Attach to any Eloquent model to automatically log lifecycle events.
 * Kanban events (moved, reordered, stage_*) are NOT covered here —
 * they are logged manually from KanbanService because they carry
 * context that Eloquent model events cannot provide.
 *
 * USAGE
 * ─────
 *   class Task extends Model {
 *       use HasActivities;
 *
 *       // Optional: exclude noisy fields from update tracking
 *       protected array $activityIgnoreFields = ['sort_order', 'extra'];
 *   }
 *
 * EVENTS AUTO-LOGGED
 * ──────────────────
 *   created  → fires after first save
 *   updated  → fires after any save that changes tracked fields
 *   deleted  → fires after soft/hard delete
 *
 * EVENTS NOT AUTO-LOGGED (log manually via ActivityLogger)
 * ─────────────────────────────────────────────────────────
 *   moved, reordered, stage_*, assigned, priority_changed, etc.
 */
trait HasActivities
{
    public static function bootHasActivities(): void
    {
        static::created(function ($model) {
            if ($model->shouldLogActivity('created')) {
                ActivityLogger::logCreated($model);
            }
        });

        static::updated(function ($model) {
            if (! $model->shouldLogActivity('updated')) {
                return;
            }

            $changes = ActivityLogger::buildChanges(
                $model,
                $model->getActivityIgnoreFields()
            );

            ActivityLogger::logUpdated($model, $changes);
            // logUpdated() is a no-op when $changes is empty — safe to always call
        });

        static::deleted(function ($model) {
            if ($model->shouldLogActivity('deleted')) {
                ActivityLogger::logDeleted($model);
            }
        });
    }

    // ── Relationship ──────────────────────────────────────────────────────────

    /**
     * All activity records for this model, newest first.
     *
     * Usage: $task->activities()->paginate(20)
     *        $task->activities()->forEvent('moved')->get()
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest();
    }

    // ── Extension points ──────────────────────────────────────────────────────

    /**
     * Override to selectively disable auto-logging for specific events.
     *
     * Example — skip delete logging:
     *   protected function shouldLogActivity(string $event): bool {
     *       return $event !== 'deleted';
     *   }
     */
    protected function shouldLogActivity(string $event): bool
    {
        return true;
    }

    /**
     * Fields to exclude from update change-tracking.
     * Reads from $activityIgnoreFields on the model if declared.
     */
    protected function getActivityIgnoreFields(): array
    {
        return $this->activityIgnoreFields ?? [];
    }
}
