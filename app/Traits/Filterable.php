<?php

namespace App\Traits;

use App\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filterable — model-side trait.
 *
 * Adds a single Eloquent scope: scopeFilter().
 * This is the only bridge between your model's query builder and the filter system.
 *
 * Usage in controller:
 *   Workspace::query()
 *       ->where('user_id', auth()->id())
 *       ->filter($filter)               ← this scope
 *       ->paginateWithFilters(...);
 *
 * Laravel resolves the filter class automatically via the controller
 * method signature — you never instantiate it manually:
 *   public function index(Request $request, WorkspaceFilter $filter)
 *
 * Apply to any model:
 *   use Filterable;
 */
trait Filterable
{
    /**
     * Hands the current query builder to the filter's apply() method.
     * Returns the same builder so the chain continues uninterrupted.
     */
    public function scopeFilter(Builder $query, BaseFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
