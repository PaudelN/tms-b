<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * BaseFilter — the core filter engine.
 *
 * FIX: Tracks consumed params so the Paginatable trait doesn't re-apply
 * them as WHERE conditions (which caused the 500 errors with created_from,
 * created_to, sort, etc.).
 *
 * Usage in controller:
 *   ->filter($filter)
 *   ->paginateWithFilters(
 *       request:       $request,
 *       ...
 *       excludeParams: $filter->consumed(), // ← pass this to Paginatable
 *   );
 *
 * If your Paginatable trait doesn't yet support excludeParams, use the
 * second option: call $filter->cleanRequest() before paginateWithFilters.
 */
abstract class BaseFilter
{
    /**
     * Params that are NEVER forwarded to filter methods.
     * Paginatable handles these itself (page, sort_by, sort_order, search)
     * or they conflict with Eloquent (kanban_stage is controller-handled).
     */
    protected array $excluded = [
        'page',
        'per_page',
        'search',
        'sort_by',
        'sort_order',
        'sort',          // handled via explicit $this->sort() at end of apply()
        'kanban_stage',  // handled by the controller before filter runs
    ];

    /**
     * Params that were processed in this request cycle.
     * Exposed via consumed() so the controller/Paginatable can skip them.
     */
    private array $consumedParams = [];

    public function __construct(protected Request $request) {}

    /**
     * Entry point called by Filterable::scopeFilter().
     *
     * After running, all filter-consumed param keys are available via
     * consumed() so Paginatable can exclude them from its own WHERE pass.
     */
    final public function apply(Builder $query): Builder
    {
        $this->consumedParams = [];

        foreach ($this->request->all() as $param => $value) {
            if ($this->shouldSkip($param, $value)) {
                continue;
            }

            if (method_exists($this, $param)) {
                $this->{$param}($query, $value);
                $this->consumedParams[] = $param;
            }
        }

        // sort is excluded from the dispatch loop but handled explicitly here.
        // We still mark it consumed so Paginatable skips it.
        $this->sort($query);
        if ($this->request->filled('sort')) {
            $this->consumedParams[] = 'sort';
        }

        return $query;
    }

    /**
     * Returns the list of request param keys consumed by this filter.
     *
     * Pass this to paginateWithFilters as `excludeParams` so the Paginatable
     * trait doesn't apply them as raw WHERE conditions:
     *
     *   ->paginateWithFilters(
     *       request:       $request,
     *       searchable:    ['name', 'description'],
     *       excludeParams: $filter->consumed(),
     *   );
     */
    public function consumed(): array
    {
        return array_unique($this->consumedParams);
    }

    /**
     * Alternative to passing consumed() to Paginatable:
     * call this BEFORE paginateWithFilters to strip filter params from the
     * request so they are invisible to Paginatable's WHERE loop.
     *
     * NOTE: This mutates the request's input bag for the duration of the
     * request. Safe to use in a standard HTTP request lifecycle.
     */
    public function cleanRequest(): void
    {
        foreach ($this->consumed() as $param) {
            $this->request->request->remove($param);
            $this->request->query->remove($param);
        }
    }

    // ── Shared filters ────────────────────────────────────────────────────────

    /**
     * ?creator=1
     * Filters by the record owner (user_id column).
     */
    protected function creator(Builder $query, mixed $value): void
    {
        $query->where('user_id', $value);
    }

    /**
     * ?created_at=2024-03-15
     * Exact single-day filter.
     */
    protected function created_at(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '=', $value);
    }

    /**
     * ?created_from=2024-01-01
     * Inclusive range start.
     */
    protected function created_from(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '>=', $value);
    }

    /**
     * ?created_to=2024-12-31
     * Inclusive range end.
     */
    protected function created_to(Builder $query, mixed $value): void
    {
        $query->whereDate('created_at', '<=', $value);
    }

    /**
     * ?tags[]=1&tags[]=2
     * Filters to records that have ALL the given tags (whereHas).
     * Requires the model to have a tags() relationship.
     */
    protected function tags(Builder $query, mixed $value): void
    {
        $ids = is_array($value) ? $value : explode(',', $value);
        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return;
        }

        $query->whereHas('tags', fn ($q) => $q->whereIn('id', $ids));
    }

    /**
     * ?sort=asc|desc
     * Orders by created_at. Always applied after all filters.
     * sort_by / sort_order (column-level) is handled by Paginatable separately.
     *
     * NOTE: 'sort' is in $excluded so the dispatch loop skips it.
     *       apply() calls this explicitly at the end.
     */
    protected function sort(Builder $query): void
    {
        if ($this->request->filled('sort')) {
            $direction = $this->request->input('sort') === 'asc' ? 'asc' : 'desc';
            $query->orderBy('created_at', $direction);
        }
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function shouldSkip(string $param, mixed $value): bool
    {
        return in_array($param, $this->excluded)
            || $value === null
            || $value === '';
    }
}
