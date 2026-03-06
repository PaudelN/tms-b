<?php

namespace App\Http\Controllers\Api;

use App\Contracts\KanbanEntity;
use App\Http\Controllers\Controller;
use App\Services\KanbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abstract base controller that provides kanban endpoints for any entity.
 *
 * Usage:
 *   class WorkspaceController extends KanbanController {
 *       protected function kanbanModelClass(): string { return Workspace::class; }
 *   }
 *
 * This gives WorkspaceController three free endpoints:
 *   POST /workspaces/kanban/move     → kanbanMove()
 *   POST /workspaces/kanban/reorder  → kanbanReorder()
 *
 * The fetch (GET) is handled by the entity's own index() method
 * with the `kanban_stage` query parameter — same endpoint for UiTable,
 * UiList, and UiKanban. Full data consistency across all views.
 *
 * Why abstract class instead of trait:
 *   - Constructor injection works cleanly (KanbanService injected once)
 *   - Full IDE resolution of all methods
 *   - Per-entity override via clean parent:: calls, no trait conflict syntax
 *   - Properly testable with mocked KanbanService
 */
abstract class KanbanController extends Controller
{
    public function __construct(
        protected readonly KanbanService $kanban
    ) {}

    /**
     * Return the model class this controller handles kanban for.
     * Must return a class that implements KanbanEntity.
     */
    abstract protected function kanbanModelClass(): string;

    // ── Endpoints ─────────────────────────────────────────────────────────────

    /**
     * POST /entity/kanban/move
     *
     * Move a card to a different stage column.
     * Called after vuedraggable makes the optimistic UI update.
     * On failure the frontend rolls back the visual change.
     *
     * Body: { model_id: int, column_id: string }
     */
    public function kanbanMove(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model_id'  => 'required|integer',
            'column_id' => 'required',
        ]);

        $modelClass = $this->kanbanModelClass();
        $this->assertKanbanEntity($modelClass);

        /** @var \Illuminate\Database\Eloquent\Model&KanbanEntity $model */
        $model = $modelClass::findOrFail($data['model_id']);

        try {
            $updated = $this->kanban->moveCard($model, $data['column_id']);
        } catch (\Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 422;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json([
            'success' => true,
            'data'    => $updated,
        ]);
    }

    /**
     * POST /entity/kanban/reorder
     *
     * Persist the new card order within one stage.
     * Called after an intra-stage drag completes.
     *
     * Body: {
     *   stage_value:      string    — which stage is being reordered
     *   ordered_ids:      int[]     — complete ordered ID list for that stage
     *   last_ordered_at:  string?   — ISO timestamp for optimistic lock (optional)
     * }
     */
    public function kanbanReorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stage_value'     => 'required|string',
            'ordered_ids'     => 'required|array|min:1',
            'ordered_ids.*'   => 'integer',
            'last_ordered_at' => 'sometimes|nullable|date',
        ]);

        $modelClass = $this->kanbanModelClass();
        $this->assertKanbanEntity($modelClass);

        try {
            $this->kanban->reorderCards(
                modelClass:    $modelClass,
                stageValue:    $data['stage_value'],
                orderedIds:    $data['ordered_ids'],
                lastOrderedAt: $data['last_ordered_at'] ?? null
            );
        } catch (\Exception $e) {
            $status = $e->getCode() === 409 ? 409 : 422;
            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(['success' => true]);
    }

    // ── Guard ─────────────────────────────────────────────────────────────────

    private function assertKanbanEntity(string $class): void
    {
        if (!is_subclass_of($class, KanbanEntity::class)) {
            abort(500, "{$class} must implement the KanbanEntity interface.");
        }
    }
}
