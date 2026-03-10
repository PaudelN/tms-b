<?php

namespace App\Services;

use App\Contracts\KanbanEntity;
use App\Models\KanbanOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * All kanban business logic lives here.
 *
 * Injected via constructor into KanbanController.
 * Also called from HasKanban boot hooks for order initialization.
 *
 * Three main operations:
 *   fetchStage()    — paginated fetch with kanban ordering (no JOIN)
 *   moveCard()      — move entity to a new stage with hooks lifecycle
 *   reorderCards()  — persist new visual order via single bulk upsert
 */
class KanbanService
{
    // ── Fetch ─────────────────────────────────────────────────────────────────

    /**
     * Fetch one page of cards for a specific kanban stage.
     *
     * Accepts a pre-built query so the controller can apply its own
     * scopes (user_id filter, tenancy scopes, etc.) before we add
     * kanban ordering on top. This makes it work with the SAME index
     * endpoint that UiTable and UiList use — full data consistency.
     *
     * @param  Builder  $query  Base query with all business filters applied
     * @param  string  $stageValue  The stage to filter + order by
     * @param  string  $stageField  The column on the entity table (e.g. 'status')
     */
    public function fetchStage(
        Builder $query,
        string $stageValue,
        string $stageField,
        int $page = 1,
        int $perPage = 10
    ): LengthAwarePaginator {
        $table = $query->getModel()->getTable();
        $entityType = get_class($query->getModel());

        // Step 1: get ordered IDs from kanban_orders (index-only scan)
        $orderedIds = KanbanOrder::getOrderedIds($entityType, $stageValue);

        // Step 2: apply stage filter
        $query->where("{$table}.{$stageField}", $stageValue);

        // Step 3: apply visual ordering
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
     * Full lifecycle:
     *   1. kanbanCanMove()   → 403 if false
     *   2. kanbanBeforeMove() → model can throw \Exception to abort
     *   3. entity.update(stageField → newStageValue)
     *   4. KanbanOrder::setOrder() → append to bottom of destination stage
     *   5. kanbanAfterMove()  → logged on failure, never blocks response
     *
     * @throws \Exception code 403 for permission denied, 422 for validation failure
     */
    public function moveCard(Model&KanbanEntity $model, mixed $newStageValue): Model
    {
        // Authorization
        if (! $model->kanbanCanMove($newStageValue)) {
            throw new \Exception('This item cannot be moved to that stage.', 403);
        }

        // Before hook — model throws here to block the move
        $model->kanbanBeforeMove($newStageValue);

        // Normalize to string for kanban_orders (handles backed enums)
        $stageString = $newStageValue instanceof \BackedEnum
            ? $newStageValue->value
            : (string) $newStageValue;

        // Persist stage change on the entity
        $model->update([
            $model->kanbanColumnField() => $newStageValue,
        ]);

        // Sync kanban_orders: update stage + append to bottom of new stage.
        // Note: HasKanban::updated boot hook also does this, but calling
        // explicitly here ensures it runs even if the hook is bypassed.
        // updateOrCreate is idempotent — double-firing is harmless.
        $max = KanbanOrder::getMaxOrder(get_class($model), $stageString);

        KanbanOrder::setOrder(
            get_class($model),
            $model->id,
            $stageString,
            $max + 1
        );

        // After hook — non-blocking
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

        return $model->fresh();
    }

    // ── Reorder ───────────────────────────────────────────────────────────────

    /**
     * Persist the new visual order for all cards within one stage.
     *
     * Receives the complete ordered ID array as the user sees it.
     * Uses a single DB::upsert — ONE round-trip regardless of column size.
     *
     * Optimistic lock (optional):
     *   Pass $lastOrderedAt (ISO timestamp from when the frontend last loaded/reordered).
     *   If the server's kanban_orders for this stage have a newer updated_at,
     *   we reject with 409 so the frontend knows to reload before overwriting
     *   someone else's drag order.
     *
     * @param  string  $modelClass  Fully-qualified model class
     * @param  string  $stageValue  The stage these IDs belong to
     * @param  array  $orderedIds  Complete ordered ID list (what the user sees)
     * @param  string|null  $lastOrderedAt  ISO timestamp for optimistic lock (optional)
     *
     * @throws \Exception code 409 on concurrent edit conflict
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

        // Optional optimistic lock check
        if ($lastOrderedAt !== null) {
            $latestUpdate = KanbanOrder::getLatestUpdate($modelClass, $stageValue);

            if ($latestUpdate && $latestUpdate > $lastOrderedAt) {
                throw new \Exception(
                    'The column order was changed by another user. Please refresh.',
                    409
                );
            }
        }

        $now = now()->toDateTimeString();

        // Build upsert payload — position = array index
        $rows = array_values(array_map(
            fn (int $id, int $index) => [
                'entity_type' => $modelClass,
                'entity_id' => $id,
                'stage_value' => $stageValue,
                'sort_order' => $index,
                'updated_at' => $now,
                'created_at' => $now,
            ],
            $orderedIds,
            array_keys($orderedIds)
        ));

        // Single upsert — conflict on (entity_type, entity_id)
        DB::table('kanban_orders')->upsert(
            $rows,
            ['entity_type', 'entity_id'],
            ['stage_value', 'sort_order', 'updated_at']
        );
    }

    // ── Initialize (called from boot hook) ────────────────────────────────────

    /**
     * Create the initial kanban_orders row for a newly created entity.
     *
     * Also public so it can be called directly from the backfill command.
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

    // ── Private helpers ───────────────────────────────────────────────────────

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
                ->map(fn ($id, $pos) => 'WHEN '.(int) $id.' THEN '.$pos)
                ->implode(' ');

            $query
                ->orderByRaw("CASE {$table}.id {$cases} ELSE 999999 END ASC")
                ->orderBy("{$table}.created_at", 'desc');
        }
    }
}
