<?php

namespace App\Http\Controllers\Api;

use App\Enums\PipelineStatus;
use App\Filters\PipelineFilter;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pipeline\CreateRequest;
use App\Http\Requests\Pipeline\UpdateRequest;
use App\Http\Resources\Pipeline\DetailResource;
use App\Http\Resources\Pipeline\ListResource;
use App\Models\Pipeline;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * PipelineController
 *
 * No kanban — extends the base Controller, not KanbanController.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SHALLOW ROUTING — why the signatures differ between actions
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Routes file uses ->shallow(), splitting the resource into two groups:
 *
 *   NESTED  (project IS in the URL → Laravel binds both params):
 *     GET  /projects/{project}/pipelines          → index()
 *     POST /projects/{project}/pipelines          → store()
 *     GET  /projects/{project}/pipelines/counts   → counts()
 *
 *   SHALLOW (project is NOT in the URL → only {pipeline} is bound):
 *     GET  /pipelines/{pipeline}         → show()
 *     POST /pipelines/{pipeline}/update  → update()   ← POST to avoid CORS preflight
 *     DELETE /pipelines/{pipeline}       → destroy()
 *
 * ⚠️  CRITICAL: shallow actions (show / update / destroy) must NOT
 *     type-hint `Project $project`. No {project} segment exists in those
 *     URLs — Laravel would throw a 404 before your code runs.
 *
 * Hierarchy: Workspace → Project → Pipeline → PipelineStage (later)
 * ─────────────────────────────────────────────────────────────────────────────
 */
class PipelineController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // NESTED actions — {project} IS in the URL, both params bound
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /projects/{project}/pipelines
     *
     * Paginated list scoped to the project.
     *
     * Query params:
     *   ?status=1|0
     *   ?creator=1
     *   ?created_from=2024-01-01  ?created_to=2024-12-31
     *   ?search=keyword
     *   ?sort_by=name&sort_order=asc
     *   ?page=1&per_page=15
     */
    public function index(Request $request, Project $project, PipelineFilter $filter)
    {
        $paginator = Pipeline::query()
            ->forProject($project->id)
            ->with('creator')
            ->filter($filter)
            ->tap(fn ($q) => $filter->cleanRequest())
            ->paginateWithFilters(
                request:           $request,
                searchableColumns: ['name', 'description'],
                defaultSortBy:     'created_at',
                defaultSortOrder:  'desc',
            );

        return Pipeline::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * GET /projects/{project}/pipelines/counts
     *
     * Per-status counts scoped to the project.
     * Useful for the index header stats bar.
     *
     * Response: { "data": { "1": 4, "0": 1 } }
     *   where keys are PipelineStatus integer values.
     */
    public function counts(Request $request, Project $project, PipelineFilter $filter)
    {
        $baseQuery = Pipeline::query()
            ->forProject($project->id)
            ->filter($filter);

        $filter->cleanRequest();

        $result = [];
        foreach (PipelineStatus::cases() as $status) {
            $result[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        // Also expose total for convenience
        $result['total'] = array_sum($result);

        return ApiResponse::successData($result);
    }

    /**
     * POST /projects/{project}/pipelines
     */
    public function store(CreateRequest $request, Project $project)
    {
        try {
            $pipeline = Pipeline::create([
                'project_id'  => $project->id,
                'created_by'  => auth()->id(),
                'name'        => $request->name,
                'description' => $request->description,
                'status'      => $request->status,
                'extras'      => $request->extras,
            ]);

            $pipeline->load('creator', 'project', 'project.workspace');

            return ApiResponse::created(
                new DetailResource($pipeline),
                'Pipeline created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create pipeline');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHALLOW actions — {project} is NOT in the URL, only {pipeline} is bound
    //
    // Do NOT add `Project $project` to these signatures.
    // Doing so causes Laravel route-model binding to throw 404
    // because there is no {project} segment to resolve against.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /pipelines/{pipeline}    ← shallow
     */
    public function show(Pipeline $pipeline)
    {
        $pipeline->load('creator', 'project', 'project.workspace');
        // $pipeline->loadCount('stages');

        return ApiResponse::successData(new DetailResource($pipeline));
    }

    /**
     * POST /pipelines/{pipeline}/update    ← shallow, POST to avoid CORS preflight
     *
     * Note: PATCH is also registered by ->shallow() but the frontend should
     * use POST /pipelines/{pipeline}/update to sidestep CORS preflight issues.
     */
    public function update(UpdateRequest $request, Pipeline $pipeline)
    {
        try {
            $pipeline->update($request->validated());
            $pipeline->load('creator', 'project', 'project.workspace');

            return ApiResponse::successData(
                new DetailResource($pipeline),
                'Pipeline updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update pipeline');
        }
    }

    /**
     * DELETE /pipelines/{pipeline}    ← shallow
     */
    public function destroy(Pipeline $pipeline)
    {
        try {
            $pipeline->delete();
            return ApiResponse::success('Pipeline deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete pipeline');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enum endpoint — no model binding
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /enums/pipeline-statuses
     */
    public function statuses()
    {
        return ApiResponse::successData(PipelineStatus::toArray());
    }
}
