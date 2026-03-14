<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Filters\TaskFilter;
use App\Helpers\ApiResponse;
use App\Http\Requests\Task\CreateRequest;
use App\Http\Requests\Task\UpdateRequest;
use App\Http\Resources\Task\DetailResource;
use App\Http\Resources\Task\ListResource;
use App\Models\Pipeline;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * TaskController
 *
 * Extends KanbanController — gets kanbanMove() + kanbanReorder() for free.
 * The kanban column field for tasks is pipeline_stage_id.
 * The column key on the frontend is String(stage.id).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SHALLOW ROUTING
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   NESTED (pipeline IS in the URL):
 *     GET  /pipelines/{pipeline}/tasks              → index()
 *     POST /pipelines/{pipeline}/tasks              → store()
 *     POST /pipelines/{pipeline}/tasks/kanban/move    → kanbanMove()
 *     POST /pipelines/{pipeline}/tasks/kanban/reorder → kanbanReorder()
 *
 *   SHALLOW (only {task} in URL):
 *     GET    /tasks/{task}          → show()
 *     POST   /tasks/{task}/update   → update()
 *     DELETE /tasks/{task}          → destroy()
 */
class TaskController extends KanbanController
{
    protected function kanbanModelClass(): string
    {
        return Task::class;
    }

    // ── NESTED actions ────────────────────────────────────────────────────────

    /**
     * GET /pipelines/{pipeline}/tasks
     *
     * Same endpoint serves UiTable, UiList, and UiKanban.
     * When ?kanban_stage={stageId} is present, KanbanService handles
     * ordering via kanban_orders. Otherwise standard pagination applies.
     *
     * Query params:
     *   ?kanban_stage=42          — fetch one column for UiKanban
     *   ?priority=high,critical   — filter by priority
     *   ?stage=42,43              — filter by stage IDs
     *   ?search=keyword
     *   ?sort_by=due_date&sort_order=asc
     *   ?page=1&per_page=50
     */
    public function index(Request $request, Pipeline $pipeline, TaskFilter $filter)
    {
        $baseQuery = Task::query()
            ->forPipeline($pipeline->id)
            ->with('creator', 'stage')
            ->filter($filter);

        $filter->cleanRequest();

        if ($request->filled('kanban_stage')) {
            $paginator = $this->kanban->fetchStage(
                query: $baseQuery,
                stageValue: $request->kanban_stage,
                stageField: 'pipeline_stage_id',
                page: (int) $request->get('page', 1),
                perPage: (int) $request->get('per_page', 50),
            );

            return Task::formatPaginatedResponse($paginator, ListResource::class);
        }

        $paginator = $baseQuery->paginateWithFilters(
            request: $request,
            searchableColumns: ['title', 'description', 'task_number'],
            defaultSortBy: 'sort_order',
            defaultSortOrder: 'asc',
        );

        return Task::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * POST /pipelines/{pipeline}/tasks
     */
    public function store(CreateRequest $request, Pipeline $pipeline)
    {
        try {
            $task = Task::create([
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $request->pipeline_stage_id,
                'project_id' => $pipeline->project_id,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority ?? TaskPriority::MEDIUM->value,
                'due_date' => $request->due_date,
                'extra' => $request->extra,
                'created_by' => auth()->id(),
            ]);

            $task->load('creator', 'stage', 'pipeline', 'project');

            return ApiResponse::created(
                new DetailResource($task),
                'Task created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create task');
        }
    }

    // ── SHALLOW actions ───────────────────────────────────────────────────────

    /**
     * GET /tasks/{task}
     */
    public function show(Task $task)
    {
        $task->load('creator', 'updater', 'stage', 'pipeline', 'project');

        return ApiResponse::successData(new DetailResource($task));
    }

    /**
     * POST /tasks/{task}/update
     */
    public function update(UpdateRequest $request, Task $task)
    {
        try {
            $task->update([
                ...$request->only([
                    'title', 'description', 'priority',
                    'due_date', 'extra',
                ]),
                // Stage change via update form (not kanban drag) —
                // HasKanban boot hook keeps kanban_orders in sync automatically.
                ...($request->has('pipeline_stage_id')
                    ? ['pipeline_stage_id' => $request->pipeline_stage_id]
                    : []),
                'updated_by' => auth()->id(),
            ]);

            $task->load('creator', 'updater', 'stage', 'pipeline', 'project');

            return ApiResponse::successData(
                new DetailResource($task),
                'Task updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update task');
        }
    }

    /**
     * DELETE /tasks/{task}
     */
    public function destroy(Task $task)
    {
        try {
            $task->delete();

            return ApiResponse::success('Task deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete task');
        }
    }

    // ── Enum endpoint ─────────────────────────────────────────────────────────

    /**
     * GET /enums/task-priorities
     */
    public function priorities(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::successData(TaskPriority::toArray());
    }
}
