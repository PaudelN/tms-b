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
    
    Route::apiResource('workspaces', WorkspaceController::class);

    //users
    Route::get('/users',function(){ return User::all();});
});
