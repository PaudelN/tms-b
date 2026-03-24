<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreRequest;
use App\Http\Requests\Media\UpdateRequest;
use App\Http\Resources\Media\DetailResource;
use App\Http\Resources\Media\ListResource;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MediaController
 *
 * Handles media files attached to a project.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SHALLOW ROUTING
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   NESTED (project IS in the URL):
 *     GET  /projects/{project}/media   → index()
 *     POST /projects/{project}/media   → store()
 *
 *   SHALLOW (only {media} in URL):
 *     GET    /media/{media}            → show()
 *     PUT    /media/{media}            → update()
 *     DELETE /media/{media}            → destroy()
 */
class MediaController extends Controller
{
    /**
     * GET /projects/{project}/media
     *
     * List all media files for a project, ordered by newest first.
     * Supports ?type=image|video|document|other filtering.
     */
    public function index(Request $request, Project $project)
    {
        $query = Media::query()
            ->forProject($project->id)
            ->with('creator')
            ->orderByDesc('created_at');

        // Optional type filter
        if ($request->filled('type')) {
            $type = $request->query('type');
            match ($type) {
                'image' => $query->images(),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                'document' => $query->whereIn('mime_type', [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain',
                    'text/csv',
                ]),
                default => null,
            };
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Success',
            'data' => ListResource::collection($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }

    /**
     * POST /projects/{project}/media
     *
     * Upload one file and attach it to the project.
     * The file is stored in the "public" disk under projects/{id}/media/.
     */
    public function store(StoreRequest $request, Project $project)
    {
        try {
            $file = $request->file('file');

            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();
            $disk = 'public';

            // Generate a unique path to avoid collisions
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $directory = "projects/{$project->id}/media";
            $path = $file->storeAs($directory, $filename, $disk);

            $media = Media::create([
                'project_id' => $project->id,
                'created_by' => auth()->id(),
                'name' => $request->input('name', $originalName),
                'original_name' => $originalName,
                'path' => $path,
                'mime_type' => $mimeType,
                'size' => $size,
                'disk' => $disk,
                'extra' => $request->input('extra'),
            ]);

            $media->load('creator', 'project');

            return ApiResponse::created(
                new DetailResource($media),
                'Media uploaded successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to upload media');
        }
    }

    /**
     * GET /media/{media}   ← shallow
     *
     * Return full details for a single media file.
     */
    public function show(Media $media)
    {
        $media->load('creator', 'project');

        return ApiResponse::successData(new DetailResource($media));
    }

    /**
     * PUT /media/{media}   ← shallow
     *
     * Update the display name and/or extra metadata of a media file.
     * The actual file is never replaced — upload a new one instead.
     */
    public function update(UpdateRequest $request, Media $media)
    {
        try {
            $media->update($request->validated());

            $media->load('creator', 'project');

            return ApiResponse::successData(
                new DetailResource($media),
                'Media updated successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to update media');
        }
    }

    /**
     * DELETE /media/{media}   ← shallow
     *
     * Soft-delete the media record and remove the file from storage.
     */
    public function destroy(Media $media)
    {
        try {
            // Delete the physical file before soft-deleting the record
            Storage::disk($media->disk)->delete($media->path);

            $media->delete();

            return ApiResponse::success('Media deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::exception($e, 'Failed to delete media');
        }
    }
}
