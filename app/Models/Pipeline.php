<?php

namespace App\Models;

use App\Enums\PipelineStatus;
use App\Traits\Filterable;
use App\Traits\HasActivities;
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

class Pipeline extends Model
{
    use Filterable,HasActivities, HasFactory, HasMedia,HasSlug, Paginatable, SoftDeletes;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'slug',
        'description',
        'status',
        'extras',
    ];

    protected $casts = [
        'status' => PipelineStatus::class,
        'extras' => 'array',
    ];

     protected array $activityIgnoreFields = [
        'slug',          // auto-generated
        'extra',         // internal JSON
        'created_by',    // system-level
        'updated_at',    // noise
    ];

    // ── Slug ──────────────────────────────────────────────────────────────────
    // Slug is unique per project (enforced by the composite DB unique index).
    // spatie/sluggable handles collision suffixes (-1, -2 …) automatically.

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite()
            ->extraScope(fn ($query) => $query->where('project_id', $this->project_id));
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    /**
     * Pipeline stages — will be implemented in the next CRUD phase.
     * Defined here so eager-loads can already reference the relationship.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('display_order');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'pipeline_id')->orderBy('sort_order');
    }

    public function tasksCount(): int
    {
        return $this->tasks()->count();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', PipelineStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', PipelineStatus::INACTIVE);
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === PipelineStatus::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === PipelineStatus::INACTIVE;
    }

    public function activate(): bool
    {
        return $this->update(['status' => PipelineStatus::ACTIVE]);
    }

    public function deactivate(): bool
    {
        return $this->update(['status' => PipelineStatus::INACTIVE]);
    }
}
