<?php

namespace App\Traits;

use App\Models\KanbanOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Apply to any model that should support kanban view.
 *
 * Usage:
 *   class Workspace extends Model implements KanbanEntity {
 *       use HasKanban;
 *       public function kanbanColumnField(): string { return 'status'; }
 *   }
 *
 * What this trait provides:
 *   - Default implementations for all KanbanEntity interface methods (no-op hooks, canMove = true)
 *   - scopeForKanbanStage() — two-query pagination, no JOINs, correct COUNT(*)
 *   - Boot hooks: auto-create kanban_orders on created, sync on updated, cleanup on deleting
 */
trait HasKanban
{
    // ── Default KanbanEntity implementations ─────────────────────────────────
    // Override any of these in the model as needed.

    public function kanbanBeforeMove(mixed $newStageValue): void
    {
        // no-op — override to validate or block a move
    } 

    public function kanbanAfterMove(string $field, mixed $newStageValue): void
    {
        // no-op — override to fire events, notifications, audit logs
        // Example: WorkspaceStageChanged::dispatch($this, $newStageValue);
    }

    public function kanbanCanMove(mixed $newStageValue): bool
    {
        return true;
        // Override example: return !$this->is_locked;
    }

    // ── Boot hooks — auto-wired by Laravel on model boot ─────────────────────

    public static function bootHasKanban(): void
    {
        // New entity created → immediately give it a kanban_orders row.
        // This ensures every entity is orderable from day one.
        // Placed at the bottom of its initial stage.
        static::created(function ($model) {
            $field      = $model->kanbanColumnField();
            $stageValue = $model->{$field};

            if ($stageValue === null) return;

            // Get the enum value if it's a backed enum
            $stageString = $stageValue instanceof \BackedEnum
                ? $stageValue->value
                : (string) $stageValue;

            $max = KanbanOrder::getMaxOrder(static::class, $stageString);

            KanbanOrder::setOrder(
                static::class,
                $model->id,
                $stageString,
                $max + 1
            );
        });

        // Entity's stage changed via edit form (outside kanban drag) →
        // keep kanban_orders in sync automatically.
        static::updated(function ($model) {
            $field = $model->kanbanColumnField();

            if (!$model->wasChanged($field)) return;

            $newStage = $model->{$field};
            $stageString = $newStage instanceof \BackedEnum
                ? $newStage->value
                : (string) $newStage;

            $max = KanbanOrder::getMaxOrder(static::class, $stageString);

            KanbanOrder::setOrder(
                static::class,
                $model->id,
                $stageString,
                $max + 1
            );
        });

        // Hard delete → remove the kanban_orders row so we don't accumulate orphans
        static::deleting(function ($model) {
            KanbanOrder::clearFor(static::class, $model->id);
        });
    }

    // ── Scope ─────────────────────────────────────────────────────────────────
    //
    // FIXED: Two-query pattern. No JOIN. paginate() COUNT(*) is always correct.
    //
    // Query 1 — hits kanban_orders on its composite index (index-only scan).
    //           Returns ordered ID list only. Very fast.
    //
    // Query 2 — fetches actual entity rows with ORDER BY FIELD() to preserve
    //           the visual order from Query 1.
    //           paginate() COUNT(*) runs on this clean query — no join inflation.
    //
    // Items with no kanban_orders row (newly created before migration, or
    // edge cases) fall to the bottom ordered by created_at.

    public function scopeForKanbanStage(Builder $query, mixed $stageValue): Builder
    {
        $table      = $this->getTable();
        $entityType = static::class;
        $field      = $this->kanbanColumnField();

        $stageString = $stageValue instanceof \BackedEnum
            ? $stageValue->value
            : (string) $stageValue;

        // Step 1: get ordered IDs from kanban_orders (index-only scan, very fast)
        $orderedIds = KanbanOrder::getOrderedIds($entityType, $stageString);

        // Step 2: apply stage filter to the entity query
        $query->where("{$table}.{$field}", $stageValue);

        if (empty($orderedIds)) {
            // No ordering exists yet (fresh install, no backfill run yet)
            // Fall back to newest-first so the board isn't random
            return $query->orderBy("{$table}.created_at", 'desc');
        }

        // Step 3: apply ORDER BY that preserves the exact visual order.
        // Items NOT in $orderedIds (edge case) fall to the bottom via created_at.
        $driver = $query->getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'])) {
            // MySQL/MariaDB: FIELD() returns 0 for values not in the list.
            // "= 0 ASC" pushes them to the bottom. "FIELD(...) ASC" orders the known ones.
            $idList = implode(',', array_map('intval', $orderedIds));
            $query
                ->orderByRaw("FIELD({$table}.id, {$idList}) = 0 ASC")
                ->orderByRaw("FIELD({$table}.id, {$idList}) ASC")
                ->orderBy("{$table}.created_at", 'desc');
        } else {
            // PostgreSQL / SQLite: CASE WHEN equivalent
            $cases = collect($orderedIds)
                ->map(fn($id, $pos) => "WHEN " . (int)$id . " THEN " . $pos)
                ->implode(' ');

            $query
                ->orderByRaw("CASE {$table}.id {$cases} ELSE 999999 END ASC")
                ->orderBy("{$table}.created_at", 'desc');
        }

        return $query;
    }
}
