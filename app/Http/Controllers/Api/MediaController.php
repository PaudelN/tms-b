<?php

namespace App\Http\Controllers\Api;

use App\Filters\MediaFilter;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
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
 *   GET    /api/media                  → list ALL media (global library)
 *   POST   /api/media                  → upload file, get Media record back
 *   GET    /api/media/{media}          → show a single media record
 *   PATCH  /api/media/{media}          → update alt text
 *   DELETE /api/media/{media}          → delete file + record
 *
 * ─── Polymorphic model media ──────────────────────────────────────────────
 *
 *   GET    /api/{morphType}/{morphId}/media                  → list media on a model
 *   POST   /api/{morphType}/{morphId}/media/upload           → upload + attach in one
 *   POST   /api/{morphType}/{morphId}/media/attach           → attach existing media
 *   DELETE /api/{morphType}/{morphId}/media/{media}/detach   → detach from model
 *   PATCH  /api/{morphType}/{morphId}/media/reorder          → reorder tag media
 *
 * morphType examples: tasks, users, projects, pipelines, workspaces
 *
 * ─── Route declaration order matters ─────────────────────────────────────
 *
 *   Standalone routes MUST be declared before the polymorphic
 *   {morphType}/{morphId}/media group so the literal path segment "media"
 *   is never captured as a {morphType} value. See api.php.
 */
class MediaController extends Controller
{
    public function __construct(protected MediaService $mediaService) {}

    // ══════════════════════════════════════════════════════════════════════════
    // STANDALONE CRUD — operate on the media record itself
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/media
     *
     * List ALL media records in the system (the global media library).
     *
     * ─── Query params ────────────────────────────────────────────────────────
     *   ?aggregate_type=image|video|audio|document|other
     *   ?uploaded_by={user_id}
     *   ?search=keyword        searches original_filename, filename, alt, mime_type
     *   ?sort_by=created_at|size|original_filename|mime_type
     *   ?sort_order=asc|desc   default: desc
     *   ?page=1
     *   ?per_page=20
     */
    public function index(Request $request, MediaFilter $filter): JsonResponse
    {
        $baseQuery = Media::query()
            ->with('uploader')
            ->filter($filter);

        $filter->cleanRequest();

        $paginator = $baseQuery->paginateWithFilters(
            request: $request,
            searchableColumns: ['original_filename', 'filename', 'alt', 'mime_type'],
            defaultSortBy: 'created_at',
            defaultSortOrder: 'desc',
        );

        return Media::formatPaginatedResponse($paginator, ListResource::class);

    }

    /**
     * POST /api/media
     *
     * Upload a file. Returns the new Media record.
     * NOT attached to any model yet.
     *
     * Body (multipart/form-data):
     *   file   required
     *   alt    optional
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

        return ApiResponse::created(
            new DetailResource($media->load('uploader')),
            'Media uploaded successfully.',
        );
    }

    /**
     * GET /api/media/{media}
     */
    public function show(Media $media): JsonResponse
    {
        return ApiResponse::successData(
            new DetailResource($media->load('uploader')),
        );
    }

    /**
     * PATCH /api/media/{media}
     *
     * Update alt text. Physical file is NOT touched.
     *
     * Body (JSON):
     *   alt   string|null
     */
    public function update(Request $request, Media $media): JsonResponse
    {
        $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->mediaService->update($media, $request->only('alt'));

        return ApiResponse::successData(
            new DetailResource($media),
            'Media updated.',
        );
    }

    /**
     * DELETE /api/media/{media}
     *
     * Permanently delete the physical file and its DB record.
     * Cascade on `mediables` removes all pivot rows automatically.
     */
    public function destroy(Media $media): JsonResponse
    {
        $this->mediaService->delete($media);

        return ApiResponse::success('Media deleted.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POLYMORPHIC HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve the {morphType}/{morphId} route pair to a model instance.
     * Aborts with 404 JSON if the morphType is not in the allowed map.
     */
    protected function resolveOwner(string $morphType, int $morphId): \Illuminate\Database\Eloquent\Model
    {
        $class = $this->mediaService->resolveModel($morphType);

        abort_if($class === null, 404, "Unknown resource type [{$morphType}].");

        return $class::findOrFail($morphId);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODEL-SCOPED ENDPOINTS — media attached to a specific model instance
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/{morphType}/{morphId}/media
     *
     * Intentionally named modelIndex() — not index() — to avoid collision
     * with the standalone index() above.
     *
     * Query params:
     *   ?tag=cover|attachments|default
     *   ?page=1
     *   ?per_page=20
     */
    public function modelIndex(string $morphType, int $morphId, Request $request): JsonResponse
    {
        $model = $this->resolveOwner($morphType, $morphId);
        $tag = $request->query('tag');
        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = max((int) $request->query('page', 1), 1);

        $query = $model->media()
            ->withPivot('tag', 'order')
            ->orderBy('mediables.order', 'asc')
            ->orderBy('media.created_at', 'desc');

        if ($tag) {
            $query->wherePivot('tag', $tag);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json(
            ListResource::collection($paginated)->response()->getData(true)
        );
    }

    /**
     * POST /api/{morphType}/{morphId}/media/upload
     *
     * Body (multipart/form-data):
     *   file   required
     *   tag    optional   defaults to "default"
     *   alt    optional
     */
    public function uploadAndAttach(
        StoreRequest $request,
        string $morphType,
        int $morphId,
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $morphId);
        $tag = $request->input('tag', 'default');

        $media = $this->mediaService->storeAndAttach(
            file: $request->file('file'),
            model: $model,
            tag: $tag,
            alt: $request->input('alt'),
        );

        return ApiResponse::created(
            new DetailResource($media->load('uploader')),
            'File uploaded and attached.',
        );
    }

    /**
     * POST /api/{morphType}/{morphId}/media/attach
     *
     * Body (JSON):
     *   media_id   required
     *   tag        optional   defaults to "default"
     *   order      optional
     */
    public function attach(
        AttachRequest $request,
        string $morphType,
        int $morphId,
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $morphId);

        $model->attachMedia(
            $request->integer('media_id'),
            $request->input('tag', 'default'),
            $request->input('order'),
        );

        return ApiResponse::success('Media attached.');
    }

    /**
     * DELETE /api/{morphType}/{morphId}/media/{media}/detach
     *
     * Query params:
     *   ?tag=default
     */
    public function detach(
        string $morphType,
        int $morphId,
        Media $media,
        Request $request,
    ): JsonResponse {
        $model = $this->resolveOwner($morphType, $morphId);
        $tag = $request->query('tag', 'default');

        $model->detachMedia($media, $tag);

        return ApiResponse::success('Media detached.');
    }

    /**
     * PATCH /api/{morphType}/{morphId}/media/reorder
     *
     * Body (JSON):
     *   ordered_ids   required   array of media IDs in the desired order
     *   tag           optional   defaults to "default"
     */
    public function reorder(
        Request $request,
        string $morphType,
        int $morphId,
    ): JsonResponse {
        $request->validate([
            'tag' => ['nullable', 'string', 'max:64'],
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:media,id'],
        ]);

        $model = $this->resolveOwner($morphType, $morphId);

        $model->reorderMedia(
            $request->input('ordered_ids'),
            $request->input('tag', 'default'),
        );

        return ApiResponse::success('Media reordered.');
    }

    public function counts(): JsonResponse
    {
        $rows = Media::query()
            ->selectRaw('aggregate_type, COUNT(*) as total')
            ->groupBy('aggregate_type')
            ->pluck('total', 'aggregate_type');

        // Fill in zeros for any type that has no rows yet
        $counts = collect(['image', 'video', 'audio', 'document', 'other'])
            ->mapWithKeys(fn (string $type) => [$type => (int) ($rows[$type] ?? 0)]);

        return response()->json($counts);
    }
}
