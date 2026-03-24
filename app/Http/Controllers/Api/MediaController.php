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
use App\Http\Requests\Media\AttachRequest;
use App\Http\Requests\Media\StoreRequest;
use App\Http\Resources\Media\DetailResource;
use App\Http\Resources\Media\ListResource;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MediaController
 *
 * ─── Standalone media CRUD ────────────────────────────────────────────────
 *
 *   POST   /api/media                  → upload file, get Media record back
 *   GET    /api/media/{media}          → show a single media record
 *   PATCH  /api/media/{media}          → update alt text
 *   DELETE /api/media/{media}          → delete file + record
 *
 * ─── Polymorphic model media ──────────────────────────────────────────────
 *
 *   GET    /api/{morphType}/{id}/media                  → list media on a model
 *   POST   /api/{morphType}/{id}/media/upload           → upload + attach in one
 *   POST   /api/{morphType}/{id}/media/attach           → attach existing media
 *   DELETE /api/{morphType}/{id}/media/{media}/detach   → detach from model
 *   PATCH  /api/{morphType}/{id}/media/reorder          → reorder tag media
 *
 * morphType examples: tasks, users, projects, pipelines, workspaces
 */
class MediaController extends Controller
{
    public function __construct(protected MediaService $mediaService) {}

    // ── Standalone CRUD ───────────────────────────────────────────────────────

    /**
     * POST /api/media
     * Upload a file. Returns the new Media record.
     * The file is stored at uploads/{aggregate_type}/{uuid}.{ext}
     * and is NOT attached to any model yet.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $media = $this->mediaService->store(
            file: $request->file('file'),
            directory: 'uploads/'.Media::aggregateTypeFor(
                $request->file('file')->getMimeType()
            ),
            alt: $request->input('alt'),
        );

        return ApiResponse::success(
            new DetailResource($media->load('uploader')),
            'Media uploaded successfully.',
            201,
        );
    }

    /**
     * GET /api/media/{media}
     */
    public function show(Media $media): JsonResponse
    {
        return ApiResponse::success(
            new DetailResource($media->load('uploader'))
        );
    }

    /**
     * PATCH /api/media/{media}
     * Only mutable field right now is `alt`. Extend as needed.
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->mediaService->update($media, $request->only('alt'));

        return ApiResponse::success(
            new DetailResource($media),
            'Media updated.'
        );
    }

    /**
     * DELETE /api/media/{media}
     * Deletes the physical file and the DB record.
     * Cascade on mediables removes all pivot rows automatically.
     */
    public function destroy(Media $media): JsonResponse
    {
        $this->mediaService->delete($media);

        return ApiResponse::success(null, 'Media deleted.');
    }

    // ── Polymorphic helpers ───────────────────────────────────────────────────

    /**
     * Resolve the {morphType}/{id} route pair to a model instance.
     * Returns 404 JSON if morphType is not in the allowed map.
     */
    protected function resolveOwner(string $morphType, int $id): \Illuminate\Database\Eloquent\Model
    {
        $class = $this->mediaService->resolveModel($morphType);

        abort_if($class === null, 404, "Unknown resource type [{$morphType}].");

        return $class::findOrFail($id);
    }

    // ── Model-scoped endpoints ────────────────────────────────────────────────

    /**
     * GET /api/{morphType}/{id}/media?tag=attachments
     * List all media on a model, optionally filtered by tag.
     */
    public function index(string $morphType, int $id, Request $request): JsonResponse
    {
        $model = $this->resolveOwner($morphType, $id);
        $tag = $request->query('tag');

        $media = $model->getMedia($tag);

        return ApiResponse::success(ListResource::collection($media));
    }

    /**
     * POST /api/{morphType}/{id}/media/upload
     * Upload a file and immediately attach it to the model.
     */
    public function uploadAndAttach(
        StoreRequest $request,
        string $morphType,
        int $id
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $id);
        $tag = $request->input('tag', 'default');

        $media = $this->mediaService->storeAndAttach(
            file: $request->file('file'),
            model: $model,
            tag: $tag,
            alt: $request->input('alt'),
        );

        return ApiResponse::success(
            new DetailResource($media->load('uploader')),
            'File uploaded and attached.',
            201,
        );
    }

    /**
     * POST /api/{morphType}/{id}/media/attach
     * Attach an already-uploaded media record to this model.
     * Body: { media_id: 5, tag: "attachments", order: 0 }
     */
    public function attach(
        AttachRequest $request,
        string $morphType,
        int $id
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $id);

        $model->attachMedia(
            $request->integer('media_id'),
            $request->input('tag', 'default'),
            $request->input('order'),
        );

        return ApiResponse::success(null, 'Media attached.');
    }

    /**
     * DELETE /api/{morphType}/{id}/media/{media}/detach
     * Remove the relationship between media and model (file is NOT deleted).
     */
    public function detach(
        string $morphType,
        int $id,
        Media $media,
        Request $request
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $id);
        $tag = $request->query('tag', 'default');

        $model->detachMedia($media, $tag);

        return ApiResponse::success(null, 'Media detached.');
    }

    /**
     * PATCH /api/{morphType}/{id}/media/reorder
     * Re-order media within a tag.
     * Body: { tag: "attachments", ordered_ids: [3, 1, 2] }
     */
    public function reorder(
        Request $request,
        string $morphType,
        int $id
    ): JsonResponse {
        $request->validate([
            'tag' => ['nullable', 'string', 'max:64'],
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $model = $this->resolveOwner($morphType, $id);

        $model->reorderMedia(
            $request->input('ordered_ids'),
            $request->input('tag', 'default'),
        );

        return ApiResponse::success( 'Media reordered.');
    }
}
