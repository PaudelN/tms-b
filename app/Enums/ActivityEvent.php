<?php

namespace App\Enums;

/**
 * ActivityEvent — Single Source of Truth
 *
 * Every event in the activity system is declared here.
 * This enum drives:
 *   - Event string values written to the DB
 *   - Human-readable descriptions rendered in ActivityResource
 *   - Property shape documentation (what each event stores in `properties`)
 *   - Grouping / categorization for frontend filtering
 *
 * HOW TO ADD A NEW EVENT
 * ──────────────────────
 * 1. Add a new case below with a snake_case string value
 * 2. Add its label() match arm
 * 3. Add its description() match arm (receives $causer string + $props array)
 * 4. Add its category() match arm
 * 5. Call ActivityLogger::{yourMethod}() from wherever the action happens
 * That is all. No other files need to change.
 */
enum ActivityEvent: string
{
    // ── Lifecycle (auto-fired by HasActivities trait) ─────────────────────────
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    // ── Kanban — card movement ────────────────────────────────────────────────
    case Moved = 'moved';      // card dragged to a different column
    case Reordered = 'reordered';  // card dragged within the same column

    // ── Kanban — stage/column management ─────────────────────────────────────
    case StageCreated = 'stage_created';
    case StageRenamed = 'stage_renamed';
    case StageReordered = 'stage_reordered';
    case StageDeleted = 'stage_deleted';

    // ── Assignment ────────────────────────────────────────────────────────────
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';

    // ── Priority ──────────────────────────────────────────────────────────────
    case PriorityChanged = 'priority_changed';

    // ── Due date ──────────────────────────────────────────────────────────────
    case DueDateSet = 'due_date_set';
    case DueDateChanged = 'due_date_changed';
    case DueDateRemoved = 'due_date_removed';

    // ── Completion ────────────────────────────────────────────────────────────
    case Completed = 'completed';
    case Reopened = 'reopened';

    // =========================================================================
    // LABEL  — short verb phrase, used in UI badges / filters
    // =========================================================================

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Moved => 'Moved',
            self::Reordered => 'Reordered',
            self::StageCreated => 'Column added',
            self::StageRenamed => 'Column renamed',
            self::StageReordered => 'Columns reordered',
            self::StageDeleted => 'Column deleted',
            self::Assigned => 'Assigned',
            self::Unassigned => 'Unassigned',
            self::PriorityChanged => 'Priority changed',
            self::DueDateSet => 'Due date set',
            self::DueDateChanged => 'Due date changed',
            self::DueDateRemoved => 'Due date removed',
            self::Completed => 'Completed',
            self::Reopened => 'Reopened',
        };
    }

    // =========================================================================
    // DESCRIPTION  — full sentence, rendered in ActivityResource
    //
    // $causer : resolved display name ("Alice" or "System")
    // $props  : the `properties` JSON array from the Activity record
    // =========================================================================

    public function description(string $causer, array $props = []): string
    {
        return match ($this) {

            // ── Lifecycle ─────────────────────────────────────────────────
            self::Created => "{$causer} created this",

            self::Updated => (function () use ($causer, $props): string {
                $fields = array_keys($props['changes'] ?? []);
                if (empty($fields)) {
                    return "{$causer} made changes";
                }
                $readable = implode(', ', array_map(
                    fn ($f) => str_replace('_', ' ', $f),
                    $fields
                ));

                return "{$causer} updated {$readable}";
            })(),

            self::Deleted => "{$causer} deleted this",

            // ── Kanban — card ─────────────────────────────────────────────
            self::Moved => (function () use ($causer, $props): string {
                $from = $props['from_stage'] ?? '?';
                $to = $props['to_stage'] ?? '?';

                return "{$causer} moved from {$from} to {$to}";
            })(),

            self::Reordered => (function () use ($causer, $props): string {
                $stage = $props['stage'] ?? 'the board';
                $old = $props['old_position'] ?? null;
                $new = $props['new_position'] ?? null;
                if ($old !== null && $new !== null) {
                    $dir = $new < $old ? 'up' : 'down';

                    return "{$causer} moved {$dir} in {$stage}";
                }

                return "{$causer} reordered within {$stage}";
            })(),

            // ── Kanban — stage ────────────────────────────────────────────
            self::StageCreated => (function () use ($causer, $props): string {
                $name = $props['stage_name'] ?? 'a column';

                return "{$causer} added column \"{$name}\"";
            })(),

            self::StageRenamed => (function () use ($causer, $props): string {
                $old = $props['old_name'] ?? '?';
                $new = $props['new_name'] ?? '?';

                return "{$causer} renamed column \"{$old}\" to \"{$new}\"";
            })(),

            self::StageReordered => "{$causer} reordered the board columns",

            self::StageDeleted => (function () use ($causer, $props): string {
                $name = $props['stage_name'] ?? 'a column';

                return "{$causer} deleted column \"{$name}\"";
            })(),

            // ── Assignment ────────────────────────────────────────────────
            self::Assigned => (function () use ($causer, $props): string {
                $assignee = $props['assignee_name'] ?? 'someone';

                return "{$causer} assigned to {$assignee}";
            })(),

            self::Unassigned => (function () use ($causer, $props): string {
                $assignee = $props['assignee_name'] ?? 'someone';

                return "{$causer} unassigned {$assignee}";
            })(),

            // ── Priority ──────────────────────────────────────────────────
            self::PriorityChanged => (function () use ($causer, $props): string {
                $old = $props['old_priority'] ?? '?';
                $new = $props['new_priority'] ?? '?';

                return "{$causer} changed priority from {$old} to {$new}";
            })(),

            // ── Due date ──────────────────────────────────────────────────
            self::DueDateSet => (function () use ($causer, $props): string {
                $date = $props['due_date'] ?? '?';

                return "{$causer} set due date to {$date}";
            })(),

            self::DueDateChanged => (function () use ($causer, $props): string {
                $old = $props['old_date'] ?? '?';
                $new = $props['new_date'] ?? '?';

                return "{$causer} changed due date from {$old} to {$new}";
            })(),

            self::DueDateRemoved => "{$causer} removed the due date",

            // ── Completion ────────────────────────────────────────────────
            self::Completed => "{$causer} marked this as complete",
            self::Reopened => "{$causer} reopened this",
        };
    }

    // =========================================================================
    // CATEGORY  — used for frontend tab/filter grouping
    // =========================================================================

    public function category(): string
    {
        return match ($this) {
            self::Created,
            self::Updated,
            self::Deleted => 'lifecycle',

            self::Moved,
            self::Reordered => 'kanban_card',

            self::StageCreated,
            self::StageRenamed,
            self::StageReordered,
            self::StageDeleted => 'kanban_stage',

            self::Assigned,
            self::Unassigned => 'assignment',

            self::PriorityChanged => 'priority',

            self::DueDateSet,
            self::DueDateChanged,
            self::DueDateRemoved => 'due_date',

            self::Completed,
            self::Reopened => 'status',
        };
    }

    // =========================================================================
    // FACTORY — resolve from DB string safely
    // =========================================================================

    /**
     * Resolve from a raw string value without throwing on unknown events.
     * Returns null for events that exist in the DB but not yet in the enum
     * (forward-compatibility during deploys).
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
