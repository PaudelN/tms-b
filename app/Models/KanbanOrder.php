<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Stores visual ordering for any kanban-enabled entity.
 *
 * One row per entity instance.
 * Moving to a new stage → updates stage_value + appends to bottom.
 * Reordering within a stage → updates sort_order via bulk upsert.
 */
class KanbanOrder extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'stage_value',
        'sort_order',
    ];

    // ── Relationship ──────────────────────────────────────────────────────────

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Get the ordered entity IDs for a specific type + stage.
     *
     * This is the ONLY read path the HasKanban scope uses.
     * Hits the composite index (entity_type, stage_value, sort_order) —
     * pure index-only scan, never reads actual row data.
     *
     * Returns: [14, 7, 23, 5, 11]  in visual order (top → bottom).
     * Returns: [] if no ordering exists yet (triggers fallback in scope).
     */
    public static function getOrderedIds(string $entityType, string $stageValue): array
    {
        return static::where('entity_type', $entityType)
            ->where('stage_value', $stageValue)
            ->orderBy('sort_order')
            ->pluck('entity_id')
            ->toArray();
    }

    /**
     * Get the current maximum sort_order for a stage.
     *
     * Used when appending a new card to the bottom of a stage.
     * Returns -1 when stage is empty so the first card gets sort_order = 0.
     */
    public static function getMaxOrder(string $entityType, string $stageValue): int
    {
        return (int) (static::where('entity_type', $entityType)
            ->where('stage_value', $stageValue)
            ->max('sort_order') ?? -1);
    }

    /**
     * Upsert a single entity's position.
     *
     * Conflict key is (entity_type, entity_id) — one entity, one row, one stage.
     * Moving between stages updates stage_value in place.
     * Safe to call multiple times — idempotent.
     */
    public static function setOrder(
        string $entityType,
        int $entityId,
        string $stageValue,
        int $sortOrder
    ): void {
        static::updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'stage_value' => $stageValue,
                'sort_order' => $sortOrder,
            ]
        );
    }

    /**
     * Remove the ordering row when an entity is hard-deleted.
     * Called automatically from HasKanban::bootHasKanban() on the `deleting` event.
     */
    public static function clearFor(string $entityType, int $entityId): void
    {
        static::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->delete();
    }

    /**
     * Get the latest updated_at timestamp for a stage.
     * Used by the optimistic-lock check in KanbanService::reorderCards()
     * to detect concurrent edits from multiple users.
     */
    public static function getLatestUpdate(string $entityType, string $stageValue): ?string
    {
        return static::where('entity_type', $entityType)
            ->where('stage_value', $stageValue)
            ->max('updated_at');
    }
}
