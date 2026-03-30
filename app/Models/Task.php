<?php

namespace App\Models;

use App\Contracts\KanbanEntity;
use App\Enums\TaskPriority;
use App\Traits\Filterable;
use App\Traits\HasKanban;
use App\Traits\HasMedia;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model implements KanbanEntity
{
    use Filterable, HasFactory, HasKanban, HasMedia, Paginatable,SoftDeletes;

    protected $fillable = [
        'pipeline_id',
        'pipeline_stage_id',
        'project_id',
        'task_number',
        'title',
        'description',
        'priority',
        'due_date',
        'sort_order',
        'extra',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'extra' => 'array',
        'due_date' => 'date',
        'sort_order' => 'integer',
    ];

    // ── KanbanEntity contract ─────────────────────────────────────────────────
    //
    // The kanban column key for tasks is the pipeline_stage_id.
    // When a card is dragged to a new column, KanbanService updates this field.
    // KanbanOrder stores the stage id as a string (e.g. "42") — same as
    // kanbanStages[n].value on the frontend.

    public function kanbanColumnField(): string
    {
        return 'pipeline_stage_id';
    }

    public function kanbanCanMove(mixed $newStageValue): bool
    {
        // Add WIP limit check here when needed:
        // $stage = PipelineStage::find($newStageValue);
        // if ($stage?->wip_limit) {
        //     $count = Task::where('pipeline_stage_id', $newStageValue)->count();
        //     return $count < $stage->wip_limit;
        // }
        return true;
    }

    public function kanbanBeforeMove(mixed $newStageValue): void
    {
        // Verify the target stage belongs to the same pipeline
        $stage = PipelineStage::find($newStageValue);
        if (! $stage || $stage->pipeline_id !== $this->pipeline_id) {
            throw new \Exception('Target stage does not belong to this pipeline.', 422);
        }
    }

    public function kanbanAfterMove(string $field, mixed $newStageValue): void
    {
        // Keep project_id in sync (denormalized for convenience)
        // Also update updated_by if auth user is available
        $this->updateQuietly([
            'updated_by' => auth()->id(),
        ]);
    }

    // ── Boot — task number generation ─────────────────────────────────────────
    //
    // Generates a human-readable task number per pipeline: T-0001, T-0002 …
    // Uses DB-level max to avoid collisions under concurrent inserts.

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Task $task) {
            if (empty($task->task_number)) {
                $task->task_number = static::generateTaskNumber($task->pipeline_id);
            }

            // Denormalize project_id from pipeline if not explicitly set
            if (empty($task->project_id) && $task->pipeline_id) {
                $task->project_id = Pipeline::find($task->pipeline_id)?->project_id;
            }
        });

        static::updating(function (Task $task) {
            if (empty($task->updated_by)) {
                $task->updated_by = auth()->id();
            }
        });
    }

    private static function generateTaskNumber(int $pipelineId): string
    {
        $max = static::withTrashed()
            ->where('pipeline_id', $pipelineId)
            ->lockForUpdate()
            ->max(\DB::raw("CAST(SUBSTRING_INDEX(task_number, '-', -1) AS UNSIGNED)"));

        $next = (int) $max + 1;

        return 'TSK-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->withPivot(['tag', 'order'])
            ->orderBy('mediables.order');
    }

    public function mediaByTag(string $tag): MorphToMany
    {
        return $this->media()->wherePivot('tag', $tag);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForPipeline($query, int $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    public function scopeForStage($query, int $stageId)
    {
        return $query->where('pipeline_stage_id', $stageId);
    }

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByPriority($query, string|array $priority)
    {
        $values = is_array($priority) ? $priority : [$priority];

        return $query->whereIn('priority', $values);
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', now()->toDateString());
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }
}
