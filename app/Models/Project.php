<?php

namespace App\Models;

use App\Contracts\KanbanEntity;
use App\Enums\PipelineStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Traits\Filterable;
use App\Traits\HasActivities;
use App\Traits\HasKanban;
use App\Traits\HasMedia;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Project extends Model implements KanbanEntity
{
    use Filterable,HasActivities, HasFactory, HasKanban, HasMedia, HasSlug, Paginatable, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'slug',
        'description',
        'cover_image',
        'status',
        'visibility',
        'start_date',
        'end_date',
        'extra',
    ];

    protected $casts = [
        'status' => ProjectStatus::class,
        'visibility' => ProjectVisibility::class,
        'extra' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected array $activityIgnoreFields = [
        'slug',          // auto-generated
        'cover_image',   // media handled separately
        'extra',         // internal JSON
        'created_by',    // system-level
        'updated_at',    // noise
    ];

    // ── Slug ──────────────────────────────────────────────────────────────────

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function activePipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class)
            ->where('status', PipelineStatus::ACTIVE)
            ->orderBy('created_at');
    }

    /**
     * All media attached to this project via the polymorphic mediables pivot.
     * Replaces the old hasMany(Media::class) which wrongly assumed a project_id column.
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->withPivot(['tag', 'order'])
            ->orderBy('mediables.order');
    }

    /**
     * Media filtered by a specific tag slot (e.g. "cover", "attachments").
     */
    public function mediaByTag(string $tag): MorphToMany
    {
        return $this->media()->wherePivot('tag', $tag);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ProjectStatus::IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ProjectStatus::COMPLETED);
    }

    // ── KanbanEntity contract ─────────────────────────────────────────────────

    public function kanbanColumnField(): string
    {
        return 'status';
    }

    public function kanbanCanMove(mixed $newStageValue): bool
    {
        return true;
    }

    public function kanbanAfterMove(string $field, mixed $newStageValue): void {}

    public function kanbanBeforeMove(mixed $newStageValue): void {}

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === ProjectStatus::DRAFT;
    }

    public function isInProgress(): bool
    {
        return $this->status === ProjectStatus::IN_PROGRESS;
    }

    public function isOnHold(): bool
    {
        return $this->status === ProjectStatus::ON_HOLD;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProjectStatus::CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ProjectStatus::COMPLETED;
    }

    public function markInProgress(): bool
    {
        return $this->update(['status' => ProjectStatus::IN_PROGRESS]);
    }

    public function markCompleted(): bool
    {
        return $this->update(['status' => ProjectStatus::COMPLETED]);
    }
}
