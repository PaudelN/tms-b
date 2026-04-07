<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Activity\ActivityResource;
use App\Models\Activity;
use App\Traits\Paginatable;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ActivityController
 *
 * Single controller for all entity types.
 * Entity type is resolved via $entityMap — add new types here as the app grows.
 *
 * ENDPOINTS
 * ─────────
 *   GET /api/tasks/{id}/activities
 *   GET /api/projects/{id}/activities
 *
 * QUERY PARAMS
 * ────────────
 *   per_page   int     default 20, max 50
 *   page       int     default 1
 *   category   string  lifecycle | kanban_card | kanban_stage |
 *                      assignment | priority | due_date | status
 *
 * MORPH MAP
 * ─────────
 * Entity lookup uses the morph alias (registered in AppServiceProvider)
 * so queries hit the correct rows regardless of namespace changes.
 */
class ActivityController extends Controller
{
    /**
     * Add new entity types here as the system grows.
     * Key = route segment  (matches the URL: /api/tasks/…)
     * Val = morph alias    (registered in AppServiceProvider::enforceMorphMap)
     *
     * We store the alias here — NOT the FQCN — because that is what
     * LogActivityJob writes to activities.subject_type via getMorphAlias().
     */
    private array $entityMap = [
        'tasks' => 'task',
        'projects' => 'project',
    ];

    public function index(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            // ── 1. Resolve morph alias ────────────────────────────────────
            $morphAlias = $this->entityMap[$entityType] ?? null;

            if (! $morphAlias) {
                return ApiResponse::error("Unsupported entity type: {$entityType}", 422);
            }

            // ── 2. Confirm entity exists via FQCN (for Eloquent find) ─────
            // Relation::getMorphedModel() is the inverse of getMorphAlias()
            $modelClass = Relation::getMorphedModel($morphAlias);

            if (! $modelClass || ! $modelClass::find($entityId)) {
                return ApiResponse::error('Resource not found.', 404);
            }

            // ── 3. Build query using the alias (what's in the DB) ─────────
            $perPage = min((int) $request->query('per_page', 20), 50);

            $query = Activity::query()
                ->where('subject_type', $morphAlias)   // ← alias, not FQCN
                ->where('subject_id', $entityId)
                ->with('causer:id,name')
                ->latest();

            // Optional category filter (?category=kanban_card)
            if ($request->filled('category')) {
                $query->forCategory($request->query('category'));
            }

            $paginator = $query->paginate($perPage);

            // ── 4. Format using Paginatable::formatPaginatedResponse() ────
            return Paginatable::formatPaginatedResponse($paginator, ActivityResource::class);

        } catch (Exception $e) {
            return ApiResponse::exception($e, 'Failed to fetch activities');
        }
    }
}
