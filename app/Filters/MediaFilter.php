<?php

namespace App\Filters;

class MediaFilter extends BaseFilter
{
    /**
     * Columns that MediaFilter owns and handles itself.
     * Stripped from the request before paginateWithFilters runs.
     */
    protected array $handled = [
        'aggregate_type',
        'uploaded_by',
    ];

    // ── Filter handlers ───────────────────────────────────────────────────────

    /**
     * ?aggregate_type=image|video|audio|document|other
     */
    protected function aggregateType($query, mixed $value): void
    {
        if (in_array($value, ['image', 'video', 'audio', 'document', 'other'], true)) {
            $query->where('aggregate_type', $value);
        }
    }

    /**
     * ?uploaded_by=5
     */
    protected function uploadedBy($query, mixed $value): void
    {
        $query->where('uploaded_by', (int) $value);
    }
}
