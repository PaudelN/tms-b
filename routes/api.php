<?php

use App\Http\Controllers\Api\WorkspaceController;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Workspaces ────────────────────────────────────────────────────────────
    //
    // IMPORTANT: kanban routes MUST be declared BEFORE apiResource
    // so they are not swallowed by the {workspace} route parameter.

    // Kanban operations (provided by KanbanController parent)
    Route::post('workspaces/kanban/move',    [WorkspaceController::class, 'kanbanMove']);
    Route::post('workspaces/kanban/reorder', [WorkspaceController::class, 'kanbanReorder']);


    Route::get('workspaces/counts', [WorkspaceController::class, 'counts']);

    // Standard CRUD (index handles kanban fetch via ?kanban_stage= param)
    Route::apiResource('workspaces', WorkspaceController::class);

    // Enum definitions for frontend stage/column configuration
    Route::get('enums/workspace-statuses', [WorkspaceController::class, 'statuses']);

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::get('/users', function () {
        return User::all();
    });
});
