<?php

namespace App\Filters;

class ProjectFilter extends BaseFilter
{
    /**
     * Columns that ProjectFilter owns and will handle itself.
     * These are stripped from the request before paginateWithFilters
     * runs, so they are never applied as raw WHERE conditions.
     *
     * NOTE: 'sort' is intentionally omitted — it is handled by BaseFilter.
     */
    protected array $handled = [
        'status',
        'visibility',
        'creator',
        'created_from',
        'created_to',
    ];

    // ── Filter handlers ───────────────────────────────────────────────────────
    // Each method name matches a request param key (camelCase for snake_case params).
    // BaseFilter::apply() resolves and calls them automatically.

    protected function status($query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('status', $values);
    }

    protected function visibility($query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('visibility', $values);
    }

    protected function creator($query, mixed $value): void
    {
        $query->where('created_by', $value);
    }

    protected function createdFrom($query, mixed $value): void
    {
        $query->whereDate('created_at', '>=', $value);
    }

    protected function createdTo($query, mixed $value): void
    {
        $query->whereDate('created_at', '<=', $value);
    }
}
