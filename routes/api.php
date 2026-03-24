<?php

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
    // Standalone (upload once, reuse anywhere):
    //   POST   /api/media                          upload a file
    //   GET    /api/media/{media}                  show a file record
    //   PATCH  /api/media/{media}                  update alt text
    //   DELETE /api/media/{media}                  delete file + record
    //
    // Polymorphic model attachment:
    //   GET    /api/{type}/{id}/media               list media on a model
    //   POST   /api/{type}/{id}/media/upload        upload + attach in one step
    //   POST   /api/{type}/{id}/media/attach        attach existing media
    //   DELETE /api/{type}/{id}/media/{media}/detach remove from model (keeps file)
    //   PATCH  /api/{type}/{id}/media/reorder       reorder within a tag
    //
    // {type} = tasks | users | projects | pipelines | workspaces

    // Standalone CRUD
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Polymorphic model routes
    // NOTE: The {morphType} segment must come before other shallow resource routes
    //       to avoid collision. These are specific enough that conflicts won't occur.
    Route::prefix('{morphType}/{morphId}/media')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('model.media.index');
        Route::post('upload', [MediaController::class, 'uploadAndAttach'])->name('model.media.upload');
        Route::post('attach', [MediaController::class, 'attach'])->name('model.media.attach');
        Route::delete('{media}/detach', [MediaController::class, 'detach'])->name('model.media.detach');
        Route::patch('reorder', [MediaController::class, 'reorder'])->name('model.media.reorder');
    })->whereIn('morphType', ['tasks', 'users', 'projects', 'pipelines', 'workspaces']);
    //  ^^^^ whereIn locks the segment to valid values only — prevents routing conflicts

});
