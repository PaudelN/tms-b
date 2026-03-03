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

    //workspaces
    Route::apiResource('workspaces', WorkspaceController::class);
    Route::get('/enums/workspace-statuses', [WorkspaceController::class, 'statuses']);

    //users
    Route::get('/users',function(){ return User::all();});
});
