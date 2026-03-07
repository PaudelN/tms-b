<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * WorkspaceFilter
 *
 * Inherited from BaseFilter for free:
 *   ?creator=1
 *   ?created_from=2024-01-01
 *   ?created_to=2024-12-31
 *   ?tags[]=1&tags[]=2
 *   ?sort=asc|desc
 *
 * Workspace-specific filters are added below as needed.
 * The class exists now so future additions never require touching BaseFilter
 * or any other part of the system.
 *
 * EXAMPLE — adding a status filter later:
 *
 *   protected function status(Builder $query, mixed $value): void
 *   {
 *       $query->whereIn('status', (array) $value);
 *   }
 */
class WorkspaceFilter extends BaseFilter
{
    //
}
