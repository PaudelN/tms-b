<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait Paginatable
{
    /**
     * Paginate the query with filters, search, and sorting
     */
    public function scopePaginateWithFilters(
        Builder $query,
        Request $request,
        array $searchableColumns = ['name'],
        string $defaultSortBy = 'created_at',
        string $defaultSortOrder = 'desc'
    ): LengthAwarePaginator {
        // Get pagination parameters
        $perPage = min($request->get('per_page', 15), 100);

        // Apply search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $q->orWhere($column, 'like', "%{$request->search}%");
                }
            });
        }

        // Apply dynamic filters
        $this->applyDynamicFilters($query, $request);

        // Apply sorting
        $sortBy = $request->get('sort_by', $defaultSortBy);
        $sortOrder = $request->get('sort_order', $defaultSortOrder);
        $query->orderBy($sortBy, $sortOrder);

        // Return paginator
        return $query->paginate($perPage);
    }

    /**
     * Apply dynamic filters from request
     */
    protected function applyDynamicFilters(Builder $query, Request $request): void
    {
        $excludedParams = ['page', 'per_page', 'search', 'sort_by', 'sort_order'];

        foreach ($request->all() as $key => $value) {
            if (! in_array($key, $excludedParams) && ! empty($value)) {
                $query->where($key, $value);
            }
        }
    }

    /**
     * Format paginated response
     */
    public static function formatPaginatedResponse(
        LengthAwarePaginator $paginator,
        string $resourceClass
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
