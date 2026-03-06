<?php

namespace App\Contracts;

/**
 * Contract for any model that supports kanban view.
 *
 * Apply via: implements KanbanEntity + use HasKanban
 *
 * Only kanbanColumnField() is REQUIRED — all others have
 * working defaults in the HasKanban trait.
 */
interface KanbanEntity
{
    /**
     * The database column on THIS model's table that holds the stage/status value.
     *
     * Examples:
     *   Workspace → 'status'
     *   Task      → 'stage'          (pipeline stage enum)
     *   Deal      → 'pipeline_stage_id'  (FK to pipeline_stages table)
     */
    public function kanbanColumnField(): string;

    /**
     * Called BEFORE the stage column is changed.
     * Throw an \Exception here to abort the move entirely.
     * The exception message is returned as a 422 to the frontend.
     */
    public function kanbanBeforeMove(mixed $newStageValue): void;

    /**
     * Called AFTER the stage column is successfully updated.
     * Use for: firing events, sending notifications, audit logging.
     * Failures here are logged but never block the HTTP response.
     */
    public function kanbanAfterMove(string $field, mixed $newStageValue): void;

    /**
     * Authorization check — can THIS specific record be moved to the new stage?
     * Return false → 403 JSON response to frontend.
     * Return true  → proceed.
     */
    public function kanbanCanMove(mixed $newStageValue): bool;
}
