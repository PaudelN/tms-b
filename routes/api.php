<?php

use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\WorkspaceController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {

    // ── Workspaces ────────────────────────────────────────────────────────────
    // Static segments BEFORE apiResource so {workspace} doesn't swallow them.

    Route::get('workspaces/counts', [WorkspaceController::class, 'counts']);
    Route::get('workspaces/kanban/board', [WorkspaceController::class, 'board']);
    Route::post('workspaces/kanban/move', [WorkspaceController::class, 'kanbanMove']);
    Route::post('workspaces/kanban/reorder', [WorkspaceController::class, 'kanbanReorder']);

    Route::apiResource('workspaces', WorkspaceController::class);
    //  GET    /workspaces              → index   (lightweight list, no projects)
    //  POST   /workspaces              → store
    //  GET    /workspaces/{workspace}  → show    (full detail WITH projects list)
    //  PATCH  /workspaces/{workspace}  → update
    //  DELETE /workspaces/{workspace}  → destroy

    Route::get('enums/workspace-statuses', [WorkspaceController::class, 'statuses']);

    // ── Projects ──────────────────────────────────────────────────────────────
    //
    // Shallow routing strategy:
    //
    //   Nested  (requires workspace context — creation & listing):
    //     GET    /workspaces/{workspace}/projects              → index
    //     POST   /workspaces/{workspace}/projects              → store
    //     GET    /workspaces/{workspace}/projects/counts       → counts
    //     GET    /workspaces/{workspace}/projects/kanban/board → board
    //     POST   /workspaces/{workspace}/projects/kanban/move    → kanbanMove
    //     POST   /workspaces/{workspace}/projects/kanban/reorder → kanbanReorder
    //
    //   Flat / shallow (only project ID needed — no workspace in URL):
    //     GET    /projects/{project}              → show
    //     POST   /projects/{project}/update       → update  ← POST (CORS-safe)
    //     DELETE /projects/{project}              → destroy
    //
    // Benefits:
    //   ✔ No param explosion on show/update/destroy — frontend holds project.id
    //   ✔ Cross-workspace guard still lives in ProjectController::assertBelongsToWorkspace()
    //     for nested actions; shallow actions trust route-model binding + policy
    //   ✔ Matches Linear / ClickUp API design

    Route::prefix('workspaces/{workspace}/projects')->group(function () {
        Route::get('counts', [ProjectController::class, 'counts'])
            ->name('workspaces.projects.counts');
        Route::get('kanban/board', [ProjectController::class, 'board'])
            ->name('workspaces.projects.kanban.board');
        Route::post('kanban/move', [ProjectController::class, 'kanbanMove'])
            ->name('workspaces.projects.kanban.move');
        Route::post('kanban/reorder', [ProjectController::class, 'kanbanReorder'])
            ->name('workspaces.projects.kanban.reorder');
    });

    Route::apiResource('workspaces.projects', ProjectController::class)
        ->shallow();
    //  Registers:
    //    GET    /workspaces/{workspace}/projects      → index
    //    POST   /workspaces/{workspace}/projects      → store
    //    GET    /projects/{project}                   → show    ← shallow
    //    PATCH  /projects/{project}                   → update  ← shallow
    //    DELETE /projects/{project}                   → destroy ← shallow

    // POST alias for update — avoids CORS preflight in browser clients
    Route::post('projects/{project}/update', [ProjectController::class, 'update'])
        ->name('projects.update.post');

    Route::get('enums/project-statuses', [ProjectController::class, 'statuses']);
    Route::get('enums/project-visibilities', [ProjectController::class, 'visibilities']);

    // ── Pipelines ─────────────────────────────────────────────────────────────
    //
    // Hierarchy: Workspace → Project → Pipeline → PipelineStage (next phase)
    //
    // Shallow routing strategy:
    //
    //   Nested  (requires project context — creation & listing):
    //     GET    /projects/{project}/pipelines         → index
    //     POST   /projects/{project}/pipelines         → store
    //     GET    /projects/{project}/pipelines/counts  → counts
    //
    //   Flat / shallow (only pipeline ID needed — no project in URL):
    //     GET    /pipelines/{pipeline}                 → show
    //     POST   /pipelines/{pipeline}/update          → update  ← POST (CORS-safe)
    //     DELETE /pipelines/{pipeline}                 → destroy
    //
    // Note: No kanban routes — pipelines use list/table view only.

    // Static nested routes BEFORE apiResource to avoid {pipeline} swallowing them
    Route::prefix('projects/{project}/pipelines')->group(function () {
        Route::get('counts', [PipelineController::class, 'counts'])
            ->name('projects.pipelines.counts');
    });

    Route::apiResource('projects.pipelines', PipelineController::class)
        ->shallow();
    //  Registers:
    //    GET    /projects/{project}/pipelines   → index
    //    POST   /projects/{project}/pipelines   → store
    //    GET    /pipelines/{pipeline}            → show    ← shallow
    //    PATCH  /pipelines/{pipeline}            → update  ← shallow
    //    DELETE /pipelines/{pipeline}            → destroy ← shallow

    // POST alias for update — avoids CORS preflight in browser clients
    Route::post('pipelines/{pipeline}/update', [PipelineController::class, 'update'])
        ->name('pipelines.update.post');

    Route::get('enums/pipeline-statuses', [PipelineController::class, 'statuses']);

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::get('/users', function () {
        return User::all();
    });
});
