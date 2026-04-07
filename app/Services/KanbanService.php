<?php

namespace App\Services;

use App\Contracts\KanbanEntity;
use App\Models\KanbanOrder;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KanbanService — with Activity Logging integrated
 *
 * Original kanban logic is UNCHANGED.
 * Activity logging is added at three points only:
 *
 *   moveCard()        → logMoved() after stage change persisted
 *   reorderCards()    → logReordered() per card whose position changed
 *   initializeOrder() → no logging (internal housekeeping, not a user action)
 *
 * Stage CRUD (createStage, renameStage, etc.) lives in your Stage
 * controller/service — log those there via ActivityLogger directly.
 *
 * KEY CONSTRAINT:
 *   reorderCards() uses DB::upsert on kanban_orders — it does NOT touch
 *   the entity model, so HasActivities never fires. We need the previous
 *   sort_order values to compute "what changed", so we load them before
 *   the upsert and compare after. This is the only extra DB read added.
 */
class KanbanService
{
    // ── Fetch ─────────────────────────────────────────────────────────────────

    /**
     * Fetch one page of cards for a specific kanban stage.
     * (Unchanged from original)
     */
    public function fetchStage(
        Builder $query,
        string $stageValue,
        string $stageField,
        int $page = 1,
        int $perPage = 10
    ): LengthAwarePaginator {
        $table      = $query->getModel()->getTable();
        $entityType = get_class($query->getModel());

        $orderedIds = KanbanOrder::getOrderedIds($entityType, $stageValue);

        $query->where("{$table}.{$stageField}", $stageValue);

        if (empty($orderedIds)) {
            $query->orderBy("{$table}.created_at", 'desc');
        } else {
            $this->applyKanbanOrdering($query, $table, $orderedIds);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    // ── Move ──────────────────────────────────────────────────────────────────

    /**
     * Move a card to a different stage.
     *
     * ACTIVITY LOGGING:
     *   logMoved() fires after the entity update AND the kanban_orders
     *   upsert both succeed. If either fails, no activity is logged.
     *   The $fromStageId is captured before mutation.
     *
     * Original lifecycle (hooks, KanbanOrder sync) is fully preserved.
     */
    public function moveCard(Model&KanbanEntity $model, mixed $newStageValue): Model
    {
        // ── Capture before state for activity log ─────────────────────────
        $fromStageId = (int) $model->{$model->kanbanColumnField()};

        // ── Original logic (unchanged) ────────────────────────────────────
        if (! $model->kanbanCanMove($newStageValue)) {
            throw new \Exception('This item cannot be moved to that stage.', 403);
        }

        $model->kanbanBeforeMove($newStageValue);

        $stageString = $newStageValue instanceof \BackedEnum
            ? $newStageValue->value
            : (string) $newStageValue;

        $model->update([
            $model->kanbanColumnField() => $newStageValue,
        ]);

        $max = KanbanOrder::getMaxOrder(get_class($model), $stageString);

        KanbanOrder::setOrder(
            get_class($model),
            $model->id,
            $stageString,
            $max + 1
        );

        try {
            $model->kanbanAfterMove($model->kanbanColumnField(), $newStageValue);
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                '[KanbanService] afterMove hook failed for %s #%d: %s',
                get_class($model),
                $model->id,
                $e->getMessage()
            ));
        }
        // ── End original logic ────────────────────────────────────────────

        // ── Activity log — fires after all writes succeed ─────────────────
        $toStageId = (int) $model->{$model->kanbanColumnField()};
        ActivityLogger::logMoved($model, $fromStageId, $toStageId);
        // logMoved() is a no-op if fromStageId === toStageId (guard inside)

        return $model->fresh();
    }

    // ── Reorder ───────────────────────────────────────────────────────────────

    /**
     * Persist the new visual order for all cards within one stage.
     *
     * ACTIVITY LOGGING:
     *   We load the current sort_order values for all affected cards
     *   BEFORE the upsert so we can compare old vs new positions.
     *   logReordered() is called per card — it skips internally if
     *   old_position === new_position (no noise for unchanged cards).
     *
     *   This adds ONE extra SELECT before the upsert. The tradeoff is
     *   accurate, per-card activity records vs. a single bulk "reordered"
     *   event with no position detail. Per-card is more useful in the UI.
     *
     * Original optimistic lock, upsert logic, and conflict detection
     * are fully preserved.
     */
    public function reorderCards(
        string $modelClass,
        string $stageValue,
        array $orderedIds,
        ?string $lastOrderedAt = null
    ): void {
        if (empty($orderedIds)) {
            return;
        }

        // ── Original optimistic lock (unchanged) ──────────────────────────
        if ($lastOrderedAt !== null) {
            $latestUpdate = KanbanOrder::getLatestUpdate($modelClass, $stageValue);
            if ($latestUpdate && $latestUpdate > $lastOrderedAt) {
                throw new \Exception(
                    'The column order was changed by another user. Please refresh.',
                    409
                );
            }
        }

        // ── Capture positions BEFORE upsert for activity comparison ───────
        // One query, indexed on (entity_type, stage_value) — fast.
        $previousPositions = KanbanOrder::where('entity_type', $modelClass)
            ->where('stage_value', $stageValue)
            ->whereIn('entity_id', $orderedIds)
            ->pluck('sort_order', 'entity_id')  // keyed by entity_id
            ->all();

        // ── Original upsert (unchanged) ───────────────────────────────────
        $now  = now()->toDateTimeString();
        $rows = array_values(array_map(
            fn (int $id, int $index) => [
                'entity_type' => $modelClass,
                'entity_id'   => $id,
                'stage_value' => $stageValue,
                'sort_order'  => $index,
                'updated_at'  => $now,
                'created_at'  => $now,
            ],
            $orderedIds,
            array_keys($orderedIds)
        ));

        DB::table('kanban_orders')->upsert(
            $rows,
            ['entity_type', 'entity_id'],
            ['stage_value', 'sort_order', 'updated_at']
        );
        // ── End original logic ────────────────────────────────────────────

        // ── Activity log — one event per card that actually moved ─────────
        // Resolve the stage_id for name lookup — best effort, non-blocking.
        // We get this from the model itself via the kanban column field.
        // For stage_id we query the first matching entity to get its stage.
        $stageId = (int) $modelClass::where(
            (new $modelClass)->kanbanColumnField(),
            $stageValue
        )->value((new $modelClass)->kanbanColumnField()) ?: 0;

        foreach ($orderedIds as $newPosition => $entityId) {
            $oldPosition = $previousPositions[$entityId] ?? $newPosition;
            $newPos1Based = $newPosition + 1; // convert 0-based index to 1-based

            // Resolve the model instance lazily — only if position changed
            if ((int) $oldPosition === $newPos1Based) {
                continue;
            }

            $entity = $modelClass::find($entityId);
            if (! $entity) {
                continue;
            }

            ActivityLogger::logReordered(
                $entity,
                (int) $stageValue, // stageValue IS the pipeline_stage_id for tasks
                (int) $oldPosition,
                $newPos1Based,
            );
        }
    }

    // ── Initialize (called from boot hook) ────────────────────────────────────

    /**
     * Create the initial kanban_orders row for a newly created entity.
     * No activity logged — this is internal housekeeping, not a user action.
     * (Unchanged from original)
     */
    public function initializeOrder(Model&KanbanEntity $model): void
    {
        $stageValue = $model->{$model->kanbanColumnField()};

        if ($stageValue === null) {
            return;
        }

        $stageString = $stageValue instanceof \BackedEnum
            ? $stageValue->value
            : (string) $stageValue;

        $max = KanbanOrder::getMaxOrder(get_class($model), $stageString);

        KanbanOrder::setOrder(
            get_class($model),
            $model->id,
            $stageString,
            $max + 1
        );
    }

    // ── Private helpers (unchanged) ───────────────────────────────────────────

    private function applyKanbanOrdering(Builder $query, string $table, array $orderedIds): void
    {
        $driver = $query->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $idList = implode(',', array_map('intval', $orderedIds));
            $query
                ->orderByRaw("FIELD({$table}.id, {$idList}) = 0 ASC")
                ->orderByRaw("FIELD({$table}.id, {$idList}) ASC")
                ->orderBy("{$table}.created_at", 'desc');
        } else {
            $cases = collect($orderedIds)
                ->map(fn ($id, $pos) => 'WHEN ' . (int) $id . ' THEN ' . $pos)
                ->implode(' ');

            $query
                ->orderByRaw("CASE {$table}.id {$cases} ELSE 999999 END ASC")
                ->orderBy("{$table}.created_at", 'desc');
        }
    }
}
