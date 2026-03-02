<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\CreateRequest;
use App\Http\Requests\Workspace\UpdateRequest;
use App\Http\Resources\Workspace\DetailResource;
use App\Http\Resources\Workspace\ListResource;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * Display a listing of workspaces.
     */
    public function index(Request $request)
    {;
        $paginator = Workspace::query()
            ->where('user_id', auth()->id())
            ->paginateWithFilters(
                request: $request,
                searchableColumns: ['name', 'description'],
                defaultSortBy: 'created_at',
                defaultSortOrder: 'desc'
            );

        return Workspace::formatPaginatedResponse($paginator, ListResource::class);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(CreateRequest $request)
    {
        try {
            $workspace = Workspace::create([
                'name' => $request->name,
                'description' => $request->description,
                'user_id' => auth()->id(),
            ]);

            $workspace->load('user');

            return ApiResponse::created(
                new DetailResource($workspace),
                'Workspace created successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to create workspace');
        }
    }

    /**
     * Display the specified workspace.
     */
    public function show(Workspace $workspace)
    {
        $workspace->load('user');

        return ApiResponse::successData(
            new DetailResource($workspace)
        );
    }

    /**
     * Update the specified workspace.
     */
    public function update(UpdateRequest $request, Workspace $workspace)
    {
        try {
            $workspace->update($request->validated());
            $workspace->load('user');

            return ApiResponse::successData(
                new DetailResource($workspace),
                'Workspace updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update workspace');
        }
    }

    /**
     * Remove the specified workspace.
     */
    public function destroy(Workspace $workspace)
    {
        try {
            $workspace->delete();

            return ApiResponse::success('Workspace deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete workspace');
        }
    }
}
