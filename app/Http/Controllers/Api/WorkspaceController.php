<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceStatus;
use App\Helpers\ApiResponse;
use App\Http\Requests\Workspace\CreateRequest;
use App\Http\Requests\Workspace\UpdateRequest;
use App\Http\Resources\Workspace\DetailResource;
use App\Http\Resources\Workspace\ListResource;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * WorkspaceController extends KanbanController.
 *
 * Inherits for free:
 *   kanbanMove()    → POST /workspaces/kanban/move
 *   kanbanReorder() → POST /workspaces/kanban/reorder
 *
 * The fetch is handled by index() below with ?kanban_stage=active param.
 * UiTable, UiList, UiKanban all call the SAME index() endpoint.
 * This ensures data consistency — one source of truth.
 */
class WorkspaceController extends KanbanController
{
    /**
     * Tell the parent which model's kanban we're handling.
     * This powers kanbanMove() and kanbanReorder() automatically.
     */
    protected function kanbanModelClass(): string
    {
        return Workspace::class;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /workspaces
     *
     * Unified fetch endpoint for ALL three view modes:
     *   UiTable → normal request, no special params
     *   UiList  → normal request, no special params
     *   UiKanban → adds ?kanban_stage=active (per stage, per page)
     *
     * When kanban_stage is present:
     *   - Applies forKanbanStage scope (two-query ordering, no JOIN)
     *   - Returns same paginated format as table/list
     *   - paginate() COUNT is always correct
     *
     * When kanban_stage is absent:
     *   - Normal paginateWithFilters (search, sort, filters)
     */
    public function index(Request $request)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id());

        // ── Kanban mode ──────────────────────────────────────────────────────
        if ($request->filled('kanban_stage')) {
            $paginator = $this->kanban->fetchStage(
                query:       $baseQuery,
                stageValue:  $request->kanban_stage,
                stageField:  'status',
                page:        (int) $request->get('page', 1),
                perPage:     (int) $request->get('per_page', 10),
            );

            return Workspace::formatPaginatedResponse($paginator, ListResource::class);
        }

        // ── Normal table / list mode ─────────────────────────────────────────
        $paginator = $baseQuery->paginateWithFilters(
            request:          $request,
            searchableColumns: ['name', 'description'],
            defaultSortBy:    'created_at',
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
                'name'        => $request->name,
                'description' => $request->description,
                'status'      => $request->status,
                'user_id'     => auth()->id(),
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
}
