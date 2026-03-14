<?php

namespace App\Filters;

use App\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Handles all filterable query params for tasks.
 * Applied via ->filter($filter) from the Filterable trait.
 */
class TaskFilter extends BaseFilter
{
    // ── Filter handlers — method name = query param name ──────────────────────

    protected function priority(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('priority', $values);
    }

    protected function stage(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('pipeline_stage_id', $values);
    }

    protected function creator(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('created_by', $values);
    }

    protected function due_from(Builder $query, mixed $value): void
    {
        $query->whereDate('due_date', '>=', $value);
    }

    protected function due_to(Builder $query, mixed $value): void
    {
        $query->whereDate('due_date', '<=', $value);
    }

    protected function overdue(Builder $query, mixed $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
        }
    }

    protected function created_from(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '>=', $value);
    }

    protected function created_to(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '<=', $value);
    }
}
