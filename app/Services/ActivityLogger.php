<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Jobs\LogActivityJob;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * ActivityLogger
 *
 * The single entry point for writing any activity in the system.
 * Every method maps to exactly one ActivityEvent case.
 *
 * DESIGN RULES
 * ────────────
 * 1. All methods are static — no instantiation overhead.
 * 2. All methods funnel through log() — one dispatch path.
 * 3. Each public method owns its properties array shape —
 *    callers never hand-craft raw arrays.
 * 4. Stage name resolution happens HERE, not at the call site —
 *    so KanbanService stays focused on kanban logic.
 * 5. Log AFTER the DB write succeeds — never before.
 *
 * KANBAN INTEGRATION NOTE
 * ───────────────────────
 * KanbanService uses updateQuietly() / DB::upsert() for its writes,
 * which deliberately bypass Eloquent model events. This means
 * HasActivities auto-logging does NOT fire for kanban operations.
 * ActivityLogger methods are called explicitly from KanbanService
 * after each operation completes. This is intentional — kanban
 * events carry richer context (stage names, positions) that the
 * generic `updated` event cannot express.
 */
class ActivityLogger
{
    // =========================================================================
    // CORE — all methods funnel here
    // =========================================================================

    public static function log(
        Model $subject,
        ActivityEvent $event,
        array $properties = [],
        ?int $causerId = null
    ): void {
        LogActivityJob::dispatch(
            subjectType: $subject::class,
            subjectId: $subject->getKey(),
            event: $event->value,
            properties: $properties,
            causerId: $causerId ?? Auth::id(),
        );
    }

    // =========================================================================
    // LIFECYCLE — called by HasActivities trait
    // =========================================================================

    public static function logCreated(Model $subject, ?int $causerId = null): void
    {
        static::log($subject, ActivityEvent::Created, [], $causerId);
    }

    /**
     * @param  array  $changes  Shape: ['field' => ['old' => mixed, 'new' => mixed]]
     */
    public static function logUpdated(Model $subject, array $changes, ?int $causerId = null): void
    {
        if (empty($changes)) {
            return;
        }
        static::log($subject, ActivityEvent::Updated, ['changes' => $changes], $causerId);
    }

    public static function logDeleted(Model $subject, ?int $causerId = null): void
    {
        static::log($subject, ActivityEvent::Deleted, [], $causerId);
    }

    // =========================================================================
    // KANBAN — CARD MOVEMENT
    // Called from KanbanService::moveCard() after the DB write
    // =========================================================================

    /**
     * Card dragged from one stage to another.
     *
     * Stage names are resolved here so KanbanService doesn't need to
     * know about PipelineStage — it just passes IDs.
     *
     * Properties: { from_stage_id, from_stage, to_stage_id, to_stage }
     */
    public static function logMoved(
        Model $subject,
        int $fromStageId,
        int $toStageId,
        ?int $causerId = null
    ): void {
        // Only log if there was an actual stage change
        if ($fromStageId === $toStageId) {
            return;
        }

        $fromStage = PipelineStage::find($fromStageId);
        $toStage = PipelineStage::find($toStageId);

        static::log($subject, ActivityEvent::Moved, [
            'from_stage_id' => $fromStageId,
            'from_stage' => $fromStage?->name ?? "Stage #{$fromStageId}",
            'to_stage_id' => $toStageId,
            'to_stage' => $toStage?->name ?? "Stage #{$toStageId}",
        ], $causerId);
    }

    /**
     * Card dragged to a new position within the SAME stage.
     *
     * KanbanService::reorderCards() uses DB::upsert on kanban_orders,
     * so it only passes sort_order deltas. We log one event per card
     * whose position actually changed.
     *
     * Properties: { stage_id, stage, old_position, new_position }
     */
    public static function logReordered(
        Model $subject,
        int $stageId,
        int $oldPosition,
        int $newPosition,
        ?int $causerId = null
    ): void {
        if ($oldPosition === $newPosition) {
            return; // nothing to log
        }

        $stage = PipelineStage::find($stageId);

        static::log($subject, ActivityEvent::Reordered, [
            'stage_id' => $stageId,
            'stage' => $stage?->name ?? "Stage #{$stageId}",
            'old_position' => $oldPosition,
            'new_position' => $newPosition,
        ], $causerId);
    }

    // =========================================================================
    // KANBAN — STAGE MANAGEMENT
    // Logged against the Pipeline model (not a Task)
    // Called from wherever stage CRUD happens (controller / service)
    // =========================================================================

    /**
     * A new column was added to the board.
     * Properties: { stage_id, stage_name }
     */
    public static function logStageCreated(
        Model $pipeline,
        int $stageId,
        string $stageName,
        ?int $causerId = null
    ): void {
        static::log($pipeline, ActivityEvent::StageCreated, [
            'stage_id' => $stageId,
            'stage_name' => $stageName,
        ], $causerId);
    }

    /**
     * A column was renamed.
     * Properties: { stage_id, old_name, new_name }
     * ⚠ Capture $oldName BEFORE the update call.
     */
    public static function logStageRenamed(
        Model $pipeline,
        int $stageId,
        string $oldName,
        string $newName,
        ?int $causerId = null
    ): void {
        static::log($pipeline, ActivityEvent::StageRenamed, [
            'stage_id' => $stageId,
            'old_name' => $oldName,
            'new_name' => $newName,
        ], $causerId);
    }

    /**
     * Columns were drag-reordered.
     * Properties: { order: [stageId, stageId, ...] }
     * One event for the whole operation, not one per stage.
     */
    public static function logStageReordered(
        Model $pipeline,
        array $orderedStageIds,
        ?int $causerId = null
    ): void {
        static::log($pipeline, ActivityEvent::StageReordered, [
            'order' => $orderedStageIds,
        ], $causerId);
    }

    /**
     * A column was deleted.
     * Properties: { stage_id, stage_name }
     * ⚠ Capture $stageName BEFORE the delete call.
     */
    public static function logStageDeleted(
        Model $pipeline,
        int $stageId,
        string $stageName,
        ?int $causerId = null
    ): void {
        static::log($pipeline, ActivityEvent::StageDeleted, [
            'stage_id' => $stageId,
            'stage_name' => $stageName,
        ], $causerId);
    }

    // =========================================================================
    // UTILITY — used by HasActivities trait
    // =========================================================================

    /**
     * Build a field-level change diff from Eloquent dirty state.
     *
     * @param  array  $ignore  Fields to skip on top of the defaults
     * @return array Shape: ['field' => ['old' => mixed, 'new' => mixed]]
     */
    public static function buildChanges(Model $model, array $ignore = []): array
    {
        $skip = array_merge(
            ['updated_at', 'created_at', 'updated_by', 'sort_order'],
            $ignore
        );

        $changes = [];

        foreach ($model->getDirty() as $field => $newValue) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $oldValue = $model->getOriginal($field);

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
        }

        return $changes;
    }
}
