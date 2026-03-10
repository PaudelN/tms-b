<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceStatus;
use App\Filters\WorkspaceFilter;
use App\Helpers\ApiResponse;
use App\Http\Requests\Workspace\CreateRequest;
use App\Http\Requests\Workspace\UpdateRequest;
use App\Http\Resources\Workspace\DetailResource;
use App\Http\Resources\Workspace\ListResource;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * WorkspaceController extends KanbanController.
 *
 * Inherits for free:
 *   kanbanMove()    → POST /workspaces/kanban/move
 *   kanbanReorder() → POST /workspaces/kanban/reorder
 *
 * Filter integration:
 *   WorkspaceFilter is auto-injected by Laravel's service container.
 *   It reads the current Request internally — you never instantiate it.
 *   ->filter($filter) runs before both kanban and paginate paths,
 *   so all three view modes (table, list, kanban) are filtered consistently.
 */
class WorkspaceController extends KanbanController
{
    /**
     * Tell the parent which model's kanban we're handling.
     */
    protected function kanbanModelClass(): string
    {
        return Workspace::class;
    }

    protected function status(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('status', $values);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /workspaces
     *
     * Unified fetch endpoint for ALL three view modes.
     * WorkspaceFilter is resolved and injected automatically by Laravel.
     *
     * Supported filter params:
     *   ?creator=1                          filter by owner
     *   ?created_from=2024-01-01            date range start
     *   ?created_to=2024-12-31              date range end
     *   ?tags[]=1&tags[]=2                  tag IDs (array or comma-separated)
     *   ?sort=asc|desc                      order by created_at
     *   ?search=keyword                     searched across name, description (Paginatable)
     *   ?sort_by=name&sort_order=asc        explicit column sort (Paginatable)
     *   ?kanban_stage=active                triggers kanban mode (HasKanban)
     *
     * All unknown params are silently ignored.
     */
    public function index(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        // ── CRITICAL FIX ──────────────────────────────────────────────────────
        // Strip params already consumed by WorkspaceFilter (created_from,
        // created_to, created_at, creator, sort, status, tags…) from the
        // request so paginateWithFilters does NOT apply them as raw WHERE
        // conditions on non-existent columns.
        $filter->cleanRequest();
        // ──────────────────────────────────────────────────────────────────────

        // ── Kanban mode ───────────────────────────────────────────────────────
        if ($request->filled('kanban_stage')) {
            $paginator = $this->kanban->fetchStage(
                query: $baseQuery,
                stageValue: $request->kanban_stage,
                stageField: 'status',
                page: (int) $request->get('page', 1),
                perPage: (int) $request->get('per_page', 10),
            );

            return Workspace::formatPaginatedResponse($paginator, ListResource::class);
        }

        // ── Table / List mode ─────────────────────────────────────────────────
        $paginator = $baseQuery->paginateWithFilters(
            request: $request,
            searchableColumns: ['name', 'description'],
            defaultSortBy: 'created_at',
            defaultSortOrder: 'desc'
        );

        return Workspace::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * GET /enums/workspace-statuses
     *
     * Returns all status definitions including color (hex), dot (tailwind),
     * badge (tailwind). Used by the frontend to build kanban column definitions.
     */
    public function statuses()
    {
        return ApiResponse::successData(WorkspaceStatus::toArray());
    }

    /**
     * POST /workspaces
     */
    public function store(CreateRequest $request)
    {
        try {
            $workspace = Workspace::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status,
                'user_id' => auth()->id(),
            ]);

            $workspace->load('user');

            return ApiResponse::created(
                new DetailResource($workspace),
                'Workspace created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create workspace');
        }
    }

    /**
     * GET /workspaces/{workspace}
     */
    public function show(Workspace $workspace)
    {
        $workspace->load('user');

        return ApiResponse::successData(
            new DetailResource($workspace)
        );
    }

    /**
     * PUT/PATCH /workspaces/{workspace}
     */
    public function update(UpdateRequest $request, Workspace $workspace)
    {
        try {
            $workspace->update($request->validated());
            $workspace->load('user');

            return ApiResponse::successData(
                new DetailResource($workspace),
                'Workspace updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update workspace');
        }
    }

    /**
     * DELETE /workspaces/{workspace}
     */
    public function destroy(Workspace $workspace)
    {
        try {
            $workspace->delete();

            return ApiResponse::success('Workspace deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete workspace');
        }
    }

    /**
     * GET /workspaces/counts
     *
     * Returns a single object with per-status item counts for the
     * authenticated user. Used by the frontend header stats bar across
     * all three views (table, list, kanban) without firing N requests.
     *
     * Example response:
     *   { "data": { "active": 12, "pending": 4, "on_hold": 1, "completed": 7, "archived": 2 } }
     */
    public function counts(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        $filter->cleanRequest();

        $counts = WorkspaceStatus::cases();

        $result = [];
        foreach ($counts as $status) {
            $result[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        return ApiResponse::successData($result);
    }

    public function board(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        $filter->cleanRequest();

        $perPage = min((int) $request->get('per_page', 50), 200);

        $result = [];

        foreach (WorkspaceStatus::cases() as $status) {
            // scopeForKanbanStage() is provided by HasKanban trait.
            // It handles: stage filter + KanbanOrder-based ordering + fallback to created_at.
            $paginator = (clone $baseQuery)
                ->forKanbanStage($status->value)
                ->paginate($perPage);

            $result[$status->value] = [
                'data' => ListResource::collection($paginator->items()),
                'meta' => [
                    'total' => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                ],
            ];
        }

        return ApiResponse::successData($result);
    }
}
