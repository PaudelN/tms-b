<?php

use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\PipelineStageController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Workspaces ────────────────────────────────────────────────────────────

    Route::get('workspaces/counts', [WorkspaceController::class, 'counts']);
    Route::get('workspaces/kanban/board', [WorkspaceController::class, 'board']);
    Route::post('workspaces/kanban/move', [WorkspaceController::class, 'kanbanMove']);
    Route::post('workspaces/kanban/reorder', [WorkspaceController::class, 'kanbanReorder']);

    Route::apiResource('workspaces', WorkspaceController::class);
    Route::get('enums/workspace-statuses', [WorkspaceController::class, 'statuses']);

    // ── Projects ──────────────────────────────────────────────────────────────

    Route::prefix('workspaces/{workspace}/projects')->group(function () {
        Route::get('counts', [ProjectController::class, 'counts'])->name('workspaces.projects.counts');
        Route::get('kanban/board', [ProjectController::class, 'board'])->name('workspaces.projects.kanban.board');
        Route::post('kanban/move', [ProjectController::class, 'kanbanMove'])->name('workspaces.projects.kanban.move');
        Route::post('kanban/reorder', [ProjectController::class, 'kanbanReorder'])->name('workspaces.projects.kanban.reorder');
    });

    Route::apiResource('workspaces.projects', ProjectController::class)->shallow();
    Route::post('projects/{project}/update', [ProjectController::class, 'update'])->name('projects.update.post');

    Route::get('enums/project-statuses', [ProjectController::class, 'statuses']);
    Route::get('enums/project-visibilities', [ProjectController::class, 'visibilities']);

    // ── Pipelines ─────────────────────────────────────────────────────────────

    Route::prefix('projects/{project}/pipelines')->group(function () {
        Route::get('counts', [PipelineController::class, 'counts'])->name('projects.pipelines.counts');
    });

    Route::apiResource('projects.pipelines', PipelineController::class)->shallow();
    Route::post('pipelines/{pipeline}/update', [PipelineController::class, 'update'])->name('pipelines.update.post');
    Route::get('enums/pipeline-statuses', [PipelineController::class, 'statuses']);

    // ── Pipeline stages ───────────────────────────────────────────────────────

    Route::prefix('pipelines/{pipeline}/stages')->group(function () {
        Route::get('counts', [PipelineStageController::class, 'counts'])->name('pipelines.stages.counts');
        Route::post('reorder', [PipelineStageController::class, 'reorder'])->name('pipelines.stages.reorder');
    });

    Route::apiResource('pipelines.stages', PipelineStageController::class)
        ->shallow()
        ->except(['update']);

    Route::post('stages/{stage}/update', [PipelineStageController::class, 'update'])->name('stages.update.post');
    Route::get('enums/pipeline-stage-statuses', [PipelineStageController::class, 'statuses']);

    // ── Tasks ─────────────────────────────────────────────────────────────────
    //
    // Hierarchy: Pipeline → PipelineStage (column) → Task (card)
    //
    // Nested (pipeline context required):
    //   GET  /pipelines/{pipeline}/tasks              → index   (UiTable + UiList + UiKanban)
    //   POST /pipelines/{pipeline}/tasks              → store
    //   POST /pipelines/{pipeline}/tasks/kanban/move    → kanbanMove   (KanbanController)
    //   POST /pipelines/{pipeline}/tasks/kanban/reorder → kanbanReorder (KanbanController)
    //
    // Shallow (task ID only):
    //   GET    /tasks/{task}          → show
    //   POST   /tasks/{task}/update   → update
    //   DELETE /tasks/{task}          → destroy
    //
    // Enum:
    //   GET /enums/task-priorities

    Route::prefix('pipelines/{pipeline}/tasks')->group(function () {
        // Static segments BEFORE apiResource so {task} doesn't swallow them
        Route::post('kanban/move', [TaskController::class, 'kanbanMove'])->name('pipelines.tasks.kanban.move');
        Route::post('kanban/reorder', [TaskController::class, 'kanbanReorder'])->name('pipelines.tasks.kanban.reorder');
    });

    Route::apiResource('pipelines.tasks', TaskController::class)
        ->shallow()
        ->except(['update']);
    //  Registers:
    //    GET    /pipelines/{pipeline}/tasks   → index
    //    POST   /pipelines/{pipeline}/tasks   → store
    //    GET    /tasks/{task}                 → show    ← shallow
    //    PATCH  /tasks/{task}                 → update  ← shallow (use POST alias below)
    //    DELETE /tasks/{task}                 → destroy ← shallow

    Route::post('tasks/{task}/update', [TaskController::class, 'update'])->name('tasks.update.post');
    Route::get('enums/task-priorities', [TaskController::class, 'priorities']);

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::get('/users', function () {
        return User::all();
    });
});
