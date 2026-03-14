<?php

namespace App\Http\Controllers\Api;

use App\Enums\PipelineStageStatus;
use App\Filters\PipelineStageFilter;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\PipelineStage\CreateRequest;
use App\Http\Requests\PipelineStage\ReorderRequest;
use App\Http\Requests\PipelineStage\UpdateRequest;
use App\Http\Resources\PipelineStage\DetailResource;
use App\Http\Resources\PipelineStage\ListResource;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PipelineStageController
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SHALLOW ROUTING
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   NESTED  (pipeline IS in the URL):
 *     GET  /pipelines/{pipeline}/stages           → index()
 *     POST /pipelines/{pipeline}/stages           → store()
 *     GET  /pipelines/{pipeline}/stages/counts    → counts()
 *     POST /pipelines/{pipeline}/stages/reorder   → reorder()
 *
 *   SHALLOW (pipeline NOT in the URL — only {stage} bound):
 *     GET    /stages/{stage}               → show()
 *     POST   /stages/{stage}/update        → update()  ← POST to avoid CORS preflight
 *     DELETE /stages/{stage}               → destroy()
 *
 * ⚠️  Shallow actions (show / update / destroy) must NOT type-hint
 *     `Pipeline $pipeline` — no {pipeline} segment exists in those URLs.
 *
 * Hierarchy: Workspace → Project → Pipeline → PipelineStage → Task
 * ─────────────────────────────────────────────────────────────────────────────
 */
class PipelineStageController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // NESTED actions — {pipeline} IS in the URL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /pipelines/{pipeline}/stages
     *
     * Ordered list scoped to the pipeline, sorted by display_order asc.
     *
     * Query params:
     *   ?status=1|0
     *   ?search=keyword
     *   ?sort_by=display_order&sort_order=asc   (default)
     */
    public function index(Request $request, Pipeline $pipeline, PipelineStageFilter $filter)
    {
        $paginator = PipelineStage::query()
            ->forPipeline($pipeline->id)
            ->with('creator')
            ->filter($filter)
            ->tap(fn ($q) => $filter->cleanRequest())
            ->paginateWithFilters(
                request:           $request,
                searchableColumns: ['name', 'display_name'],
                defaultSortBy:     'display_order',
                defaultSortOrder:  'asc',
            );

        return PipelineStage::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * GET /pipelines/{pipeline}/stages/counts
     *
     * Per-status counts scoped to the pipeline.
     * Response: { "data": { "1": 3, "0": 1, "total": 4 } }
     */
    public function counts(Request $request, Pipeline $pipeline, PipelineStageFilter $filter)
    {
        $baseQuery = PipelineStage::query()
            ->forPipeline($pipeline->id)
            ->filter($filter);

        $filter->cleanRequest();

        $result = [];
        foreach (PipelineStageStatus::cases() as $status) {
            $result[$status->value] = (clone $baseQuery)
                ->where('status', $status->value)
                ->count();
        }

        $result['total'] = array_sum($result);

        return ApiResponse::successData($result);
    }

    /**
     * POST /pipelines/{pipeline}/stages
     *
     * Auto-assigns display_order as max + 1 when not provided.
     */
    public function store(CreateRequest $request, Pipeline $pipeline)
    {
        try {
            $displayOrder = $request->display_order
                ?? PipelineStage::forPipeline($pipeline->id)->max('display_order') + 1;

            $stage = PipelineStage::create([
                'pipeline_id'   => $pipeline->id,
                'created_by'    => auth()->id(),
                'name'          => $request->name,
                'display_name'  => $request->display_name,
                'display_order' => $displayOrder,
                'status'        => $request->status,
                'color'         => $request->color,
                'wip_limit'     => $request->wip_limit,
                'extras'        => $request->extras,
            ]);

            $stage->load('creator', 'pipeline', 'pipeline.project', 'pipeline.project.workspace');

            return ApiResponse::created(
                new DetailResource($stage),
                'Pipeline stage created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create pipeline stage');
        }
    }

    /**
     * POST /pipelines/{pipeline}/stages/reorder
     *
     * Body: { "stages": [{ "id": 1, "display_order": 0 }, ...] }
     *
     * Runs in a transaction. Ownership guard on every row — a payload
     * cannot reorder stages belonging to a different pipeline.
     */
    public function reorder(ReorderRequest $request, Pipeline $pipeline)
    {
        try {
            DB::transaction(function () use ($request, $pipeline) {
                foreach ($request->stages as $item) {
                    PipelineStage::where('id', $item['id'])
                        ->where('pipeline_id', $pipeline->id)
                        ->update(['display_order' => $item['display_order']]);
                }
            });

            $stages = PipelineStage::forPipeline($pipeline->id)
                ->with('creator')
                ->ordered()
                ->get();

            return ApiResponse::successData(
                ListResource::collection($stages),
                'Stages reordered successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to reorder pipeline stages');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHALLOW actions — {pipeline} NOT in the URL, only {stage} bound
    //
    // Do NOT add `Pipeline $pipeline` to these signatures.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /stages/{stage}    ← shallow
     */
    public function show(PipelineStage $stage)
    {
        $stage->load('creator', 'pipeline', 'pipeline.project', 'pipeline.project.workspace');

        return ApiResponse::successData(new DetailResource($stage));
    }

    /**
     * POST /stages/{stage}/update    ← shallow, POST to avoid CORS preflight
     */
    public function update(UpdateRequest $request, PipelineStage $stage)
    {
        try {
            $stage->update($request->validated());
            $stage->load('creator', 'pipeline', 'pipeline.project', 'pipeline.project.workspace');

            return ApiResponse::successData(
                new DetailResource($stage),
                'Pipeline stage updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update pipeline stage');
        }
    }

    /**
     * DELETE /stages/{stage}    ← shallow
     */
    public function destroy(PipelineStage $stage)
    {
        try {
            $stage->delete();
            return ApiResponse::success('Pipeline stage deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete pipeline stage');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Enum endpoint
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /enums/pipeline-stage-statuses
     */
    public function statuses()
    {
        return ApiResponse::successData(PipelineStageStatus::toArray());
    }
}
