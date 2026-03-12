<?php

namespace App\Http\Controllers\Api;

use App\Enums\PipelineStatus;
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
 *     GET    /projects/{project}         → show()
 *     POST   /projects/{project}/update  → update()   ← POST (CORS-safe)
 *     DELETE /projects/{project}         → destroy()
 *
 * ⚠️  CRITICAL: shallow actions (show / update / destroy) must NOT
 *     type-hint `Workspace $workspace`.  When Laravel's route-model
 *     binding sees that type-hint but finds no {workspace} segment in
 *     the URL it throws NotFoundHttpException (404) before your code
 *     runs.
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
     * Pipelines are intentionally excluded here — this is a list row,
     * not a detail page. Use show() for the full project with pipelines.
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
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}    ← shallow
     *
     * Full project detail with pipelines list (only active pipelines
     * are included — the frontend uses these to populate task creation
     * dropdowns and the project sidebar).
     *
     * Pipelines are loaded via the activePipelines relationship so
     * inactive pipelines don't pollute the detail view. If the settings
     * page needs all pipelines regardless of status, pass ?with=all_pipelines
     * as a future extension — for now active-only is the sane default.
     */
    public function show(Project $project)
    {
        $project->load([
            'creator',
            'workspace',
            // Load only active pipelines for the detail view.
            // Each pipeline also carries its stages count so the
            // frontend can show "3 stages" without a second request.
            'activePipelines',
            // 'activePipelines' => fn ($q) => $q->withCount('stages'),
        ]);

        $project->loadCount(['pipelines', 'pipelines as active_pipelines_count' => fn ($q) => $q->where('status', PipelineStatus::ACTIVE)]);

        return ApiResponse::successData(new DetailResource($project));
    }

    /**
     * POST /projects/{project}/update  ← shallow, POST (CORS-safe)
     */
    public function update(UpdateRequest $request, Project $project)
    {
        try {
            $project->update($request->validated());

            $project->load([
                'creator',
                'workspace',
                'activePipelines',
                // 'activePipelines' => fn ($q) => $q->withCount('stages'),
            ]);

            $project->loadCount(['pipelines', 'pipelines as active_pipelines_count' => fn ($q) => $q->where('status', \App\Enums\PipelineStatus::ACTIVE)]);

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

    public function statuses()
    {
        return ApiResponse::successData(ProjectStatus::toArray());
    }

    public function visibilities()
    {
        return ApiResponse::successData(ProjectVisibility::toArray());
    }
}
