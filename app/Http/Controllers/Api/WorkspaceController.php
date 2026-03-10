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

class WorkspaceController extends KanbanController
{
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
     * Lightweight list — projects are NOT loaded here.
     * Use GET /workspaces/{workspace} for the full detail with projects.
     */
    public function index(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        $filter->cleanRequest();

        if ($request->filled('kanban_stage')) {
            $paginator = $this->kanban->fetchStage(
                query:      $baseQuery,
                stageValue: $request->kanban_stage,
                stageField: 'status',
                page:       (int) $request->get('page', 1),
                perPage:    (int) $request->get('per_page', 10),
            );

            return Workspace::formatPaginatedResponse($paginator, ListResource::class);
        }

        $paginator = $baseQuery->paginateWithFilters(
            request:           $request,
            searchableColumns: ['name', 'description'],
            defaultSortBy:     'created_at',
            defaultSortOrder:  'desc'
        );

        return Workspace::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * GET /enums/workspace-statuses
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
     *
     * Loads projects (with their creator) so the detail page can
     * render the workspace's project list without a second request.
     *
     * ProjectListResource handles the serialization of each project —
     * keeping this controller completely decoupled from project shape.
     */
    public function show(Workspace $workspace)
    {
        $workspace->load('user', 'projects.creator');

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

            // Do NOT load projects on update — caller doesn't need them.
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
     */
    public function counts(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        $filter->cleanRequest();

        $result = [];
        foreach (WorkspaceStatus::cases() as $status) {
            $result[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        return ApiResponse::successData($result);
    }

    /**
     * GET /workspaces/kanban/board
     */
    public function board(Request $request, WorkspaceFilter $filter)
    {
        $baseQuery = Workspace::query()
            ->where('user_id', auth()->id())
            ->filter($filter);

        $filter->cleanRequest();

        $perPage = min((int) $request->get('per_page', 50), 200);

        $result = [];

        foreach (WorkspaceStatus::cases() as $status) {
            $paginator = (clone $baseQuery)
                ->forKanbanStage($status->value)
                ->paginate($perPage);

            $result[$status->value] = [
                'data' => ListResource::collection($paginator->items()),
                'meta' => [
                    'total'        => $paginator->total(),
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                ],
            ];
        }

        return ApiResponse::successData($result);
    }
}
