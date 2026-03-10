<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Filters\ProjectFilter;
use App\Helpers\ApiResponse;
use App\Http\Requests\Project\CreateRequest;
use App\Http\Requests\Project\UpdateRequest;
use App\Http\Resources\Project\DetailResource;
use App\Http\Resources\Project\ListResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * ProjectController extends KanbanController.
 *
 * Inherits:
 *   kanbanMove()    → POST /workspaces/{workspace}/projects/kanban/move
 *   kanbanReorder() → POST /workspaces/{workspace}/projects/kanban/reorder
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SHALLOW ROUTING — why the signatures differ between actions
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * The routes file uses ->shallow(), which splits the resource into two groups:
 *
 *   NESTED  (workspace IS in the URL → Laravel binds both params):
 *     GET  /workspaces/{workspace}/projects          → index()
 *     POST /workspaces/{workspace}/projects          → store()
 *
 *   SHALLOW (workspace is NOT in the URL → only {project} is bound):
 *     GET    /projects/{project}  → show()
 *     PATCH  /projects/{project}  → update()
 *     DELETE /projects/{project}  → destroy()
 *
 * ⚠️  CRITICAL: shallow actions (show / update / destroy) must NOT
 *     type-hint `Workspace $workspace`.  When Laravel's route-model
 *     binding sees that type-hint but finds no {workspace} segment in
 *     the URL it throws NotFoundHttpException (404) before your code
 *     runs.  This was the root cause of the 404 errors on project
 *     detail and edit pages.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProjectController extends KanbanController
{
    /**
     * Tell the parent KanbanController which model's kanban we handle.
     */
    protected function kanbanModelClass(): string
    {
        return Project::class;
    }

    /**
     * Filter by status — called by the KanbanController parent.
     * Signature must match the parent exactly (no return type hint).
     */
    protected function status(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : [$value];
        $query->whereIn('status', $values);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NESTED actions — {workspace} IS in the URL, both params bound
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /workspaces/{workspace}/projects
     *
     * Unified fetch for table / list / kanban-column mode.
     *
     * Query params:
     *   ?status=draft|in_progress|on_hold|cancelled|completed
     *   ?visibility=private|workspace|public
     *   ?creator=1
     *   ?created_from=2024-01-01  ?created_to=2024-12-31
     *   ?search=keyword
     *   ?sort_by=name&sort_order=asc
     *   ?page=1&per_page=15
     *   ?kanban_stage=in_progress   → triggers single-column kanban fetch
     */
    public function index(Request $request, Workspace $workspace, ProjectFilter $filter)
    {
        $baseQuery = Project::query()
            ->forWorkspace($workspace->id)
            ->with('creator')
            ->filter($filter);

        $filter->cleanRequest();

        // ── Kanban single-column mode ─────────────────────────────────────
        if ($request->filled('kanban_stage')) {
            $paginator = $this->kanban->fetchStage(
                query:      $baseQuery,
                stageValue: $request->kanban_stage,
                stageField: 'status',
                page:       (int) $request->get('page', 1),
                perPage:    (int) $request->get('per_page', 10),
            );

            return Project::formatPaginatedResponse($paginator, ListResource::class);
        }

        // ── Table / List mode ─────────────────────────────────────────────
        $paginator = $baseQuery->paginateWithFilters(
            request:           $request,
            searchableColumns: ['name', 'description'],
            defaultSortBy:     'created_at',
            defaultSortOrder:  'desc'
        );

        return Project::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * GET /workspaces/{workspace}/projects/counts
     *
     * Per-status counts scoped to the workspace.
     * Used by the index page header stats bar.
     *
     * Response: { "data": { "draft": 3, "in_progress": 8, ... } }
     */
    public function counts(Request $request, Workspace $workspace, ProjectFilter $filter)
    {
        $baseQuery = Project::query()
            ->forWorkspace($workspace->id)
            ->filter($filter);

        $filter->cleanRequest();

        $result = [];
        foreach (ProjectStatus::cases() as $status) {
            $result[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        return ApiResponse::successData($result);
    }

    /**
     * GET /workspaces/{workspace}/projects/kanban/board
     *
     * Returns ALL status columns with paginated projects in one call.
     * Used by UiKanban's boardFetchFn — one request, all columns.
     */
    public function board(Request $request, Workspace $workspace, ProjectFilter $filter)
    {
        $baseQuery = Project::query()
            ->forWorkspace($workspace->id)
            ->with('creator')
            ->filter($filter);

        $filter->cleanRequest();

        $perPage = min((int) $request->get('per_page', 50), 200);
        $result  = [];

        foreach (ProjectStatus::cases() as $status) {
            $paginator = (clone $baseQuery)
                ->where('status', $status->value)
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

    /**
     * POST /workspaces/{workspace}/projects
     */
    public function store(CreateRequest $request, Workspace $workspace)
    {
        try {
            $project = Project::create([
                'workspace_id' => $workspace->id,
                'created_by'   => auth()->id(),
                'name'         => $request->name,
                'description'  => $request->description,
                'status'       => $request->status,
                'visibility'   => $request->visibility,
                'cover_image'  => $request->cover_image,
                'start_date'   => $request->start_date,
                'end_date'     => $request->end_date,
                'extra'        => $request->extra,
            ]);

            $project->load('creator', 'workspace');

            return ApiResponse::created(
                new DetailResource($project),
                'Project created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create project');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHALLOW actions — {workspace} is NOT in the URL, only {project} is bound
    //
    // Do NOT add `Workspace $workspace` to these signatures.
    // Doing so causes Laravel route-model binding to throw 404
    // because there is no {workspace} segment to resolve against.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}    ← shallow
     */
    public function show(Project $project)
    {
        $project->load('creator', 'workspace');

        return ApiResponse::successData(new DetailResource($project));
    }

    /**
     * PATCH /projects/{project}  ← shallow
     *
     * Note: Laravel's ->shallow() registers PATCH, not PUT.
     * The frontend store must use axios.patch(), not axios.put().
     */
    public function update(UpdateRequest $request, Project $project)
    {
        try {
            $project->update($request->validated());
            $project->load('creator', 'workspace');

            return ApiResponse::successData(
                new DetailResource($project),
                'Project updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update project');
        }
    }

    /**
     * DELETE /projects/{project} ← shallow
     */
    public function destroy(Project $project)
    {
        try {
            $project->delete();
            return ApiResponse::success('Project deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete project');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enum endpoints — no model binding
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /enums/project-statuses
     */
    public function statuses()
    {
        return ApiResponse::successData(ProjectStatus::toArray());
    }

    /**
     * GET /enums/project-visibilities
     */
    public function visibilities()
    {
        return ApiResponse::successData(ProjectVisibility::toArray());
    }
}
