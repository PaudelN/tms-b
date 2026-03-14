<?php

namespace App\Models;

use App\Enums\PipelineStageStatus;
use App\Traits\Filterable;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class PipelineStage extends Model
{
    use Filterable, HasFactory, HasSlug, Paginatable, SoftDeletes;

    protected $fillable = [
        'pipeline_id',
        'created_by',
        'name',
        'slug',
        'display_name',
        'display_order',
        'status',
        'color',
        'wip_limit',
        'extras',
    ];

    protected $casts = [
        'status' => PipelineStageStatus::class,
        'extras' => 'array',
        'display_order' => 'integer',
        'wip_limit' => 'integer',
    ];

    // ── Slug ──────────────────────────────────────────────────────────────────
    // Unique per pipeline — composite DB unique index is the hard guard.

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->extraScope(fn ($query) => $query->where('pipeline_id', $this->pipeline_id));
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'pipeline_stage_id')->orderBy('sort_order');
    }

    // Prevent deleting a stage that still has active tasks
    public function canBeDeleted(): bool
    {
        return ! $this->tasks()->exists();
    }

    // Uncomment when Task model exists:
    // public function tasks(): HasMany
    // {
    //     return $this->hasMany(Task::class)->orderBy('sort_order');
    // }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForPipeline($query, int $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', PipelineStageStatus::ACTIVE);
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === PipelineStageStatus::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === PipelineStageStatus::INACTIVE;
    }

    public function hasWipLimit(): bool
    {
        return $this->wip_limit !== null;
    }

    /**
     * The label rendered in the UI — display_name takes priority over name.
     */
    public function displayLabel(): string
    {
        return $this->display_name ?? $this->name;
    }

    public function activate(): bool
    {
        return $this->update(['status' => PipelineStageStatus::ACTIVE]);
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => PipelineStageStatus::INACTIVE]);
    }
}
