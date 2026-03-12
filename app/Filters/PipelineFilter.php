<?php

namespace App\Filters;

class PipelineFilter extends BaseFilter
{
    protected array $handled = [
        'status',
        'creator',
        'created_from',
        'created_to',
    ];

    protected function status($query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('status', array_map('intval', $values));
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
