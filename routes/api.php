<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\MediaController;
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

    Route::prefix('pipelines/{pipeline}/tasks')->group(function () {
        Route::post('kanban/move', [TaskController::class, 'kanbanMove'])->name('pipelines.tasks.kanban.move');
        Route::post('kanban/reorder', [TaskController::class, 'kanbanReorder'])->name('pipelines.tasks.kanban.reorder');
    });

    Route::get('tasks/my', [TaskController::class, 'myTasks'])->name('tasks.my');
    Route::get('tasks/all', [TaskController::class, 'allTasks'])->name('tasks.all');

    Route::get('enums/task-priorities', [TaskController::class, 'priorities']);

    Route::apiResource('pipelines.tasks', TaskController::class)
        ->shallow()
        ->except(['update']);

    Route::post('tasks/{task}/update', [TaskController::class, 'update'])->name('tasks.update.post');

    // ── Users ─────────────────────────────────────────────────────────────────

    Route::get('/users', function () {
        return User::all();
    });

    // ── Media ─────────────────────────────────────────────────────────────────
    //
    // ─── Standalone CRUD (the global media library) ───────────────────────────
    //
    //   GET    /api/media               list ALL media records (global library)
    //   POST   /api/media               upload a file (no model attachment)
    //   GET    /api/media/{media}       show a single record + uploader
    //   PATCH  /api/media/{media}       update alt text
    //   DELETE /api/media/{media}       delete file from disk + media row
    //                                   (cascade removes all mediables rows)
    //
    // ─── Polymorphic model attachment ─────────────────────────────────────────
    //
    //   GET    /api/{type}/{id}/media               list media attached to a model
    //   POST   /api/{type}/{id}/media/upload        upload + attach in one step
    //   POST   /api/{type}/{id}/media/attach        attach existing media { media_id, tag }
    //   DELETE /api/{type}/{id}/media/{id}/detach   remove pivot row only (file kept)
    //   PATCH  /api/{type}/{id}/media/reorder       reorder within a tag { ordered_ids }
    //
    // {type} constrained to: tasks | users | projects | pipelines | workspaces
    //
    // ⚠️  DECLARATION ORDER IS CRITICAL:
    //   The standalone routes MUST appear BEFORE the polymorphic group.
    //   If the polymorphic group came first, the literal segment "media" in
    //   GET /api/media would be matched as {morphType} = "media", which is not
    //   in the whereIn list and would 404.
    //
    // ⚠️  modelIndex vs index:
    //   MediaController has two list methods:
    //     index()       → GET /api/media          (global, all records)
    //     modelIndex()  → GET /api/{type}/{id}/media  (scoped to one model)
    //   We cannot use the same method name because both routes are registered
    //   on the same controller and apiResource-style magic would conflict.

    // ── Standalone routes (must be first) ─────────────────────────────────────
    Route::get('media/counts', [MediaController::class, 'counts'])->name('media.counts');
    Route::get('media', [MediaController::class, 'index'])->name('media.index');
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    // ── Polymorphic model routes ───────────────────────────────────────────────
    // whereIn locks {morphType} to the known entity types, preventing conflicts
    // with the standalone /media routes above and any future shallow resources.
    Route::prefix('{morphType}/{morphId}/media')
        ->whereIn('morphType', ['tasks', 'users', 'projects', 'pipelines', 'workspaces'])
        ->group(function () {
            // NOTE: maps to modelIndex(), NOT index() — see comment block above.
            Route::get('/', [MediaController::class, 'modelIndex'])->name('model.media.index');
            Route::post('upload', [MediaController::class, 'uploadAndAttach'])->name('model.media.upload');
            Route::post('attach', [MediaController::class, 'attach'])->name('model.media.attach');
            Route::delete('{media}/detach', [MediaController::class, 'detach'])->name('model.media.detach');
            Route::patch('reorder', [MediaController::class, 'reorder'])->name('model.media.reorder');
        });

    Route::get(
        '/{entityType}/{entityId}/activities',
        [ActivityController::class, 'index']
    )->name('activities.index');
});
