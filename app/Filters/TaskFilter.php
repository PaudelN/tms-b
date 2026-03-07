<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * TaskFilter
 *
 * Inherited from BaseFilter for free:
 *   ?creator=1
 *   ?created_from=2024-01-01
 *   ?created_to=2024-12-31
 *   ?tags[]=1&tags[]=2
 *   ?sort=asc|desc
 *
 * Task-specific filters:
 *   ?status[]=todo&status[]=in_progress
 *   ?priority[]=high&priority[]=critical
 *   ?assigned_to=5
 */
class TaskFilter extends BaseFilter
{
    /**
     * ?status[]=todo&status[]=in_progress
     * or ?status=todo  (single value also works)
     */
    protected function status(Builder $query, mixed $value): void
    {
        $query->whereIn('status', (array) $value);
    }

    /**
     * ?priority[]=high&priority[]=critical
     */
    protected function priority(Builder $query, mixed $value): void
    {
        $query->whereIn('priority', (array) $value);
    }

    /**
     * ?assigned_to=5
     * Filter tasks by their assignee.
     */
    protected function assigned_to(Builder $query, mixed $value): void
    {
        $query->where('assigned_to', $value);
    }
}
