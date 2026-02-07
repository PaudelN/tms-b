<?php

namespace App\Models;

use App\Enums\WorkspaceStatus;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Workspace extends Model
{
    use HasFactory, SoftDeletes, HasSlug, Paginatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'user_id',
        'status',
        'extra'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => WorkspaceStatus::class,
        'extra'  => 'array',
    ];

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Get the user that owns the workspace.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active workspaces.
     */
    public function scopeActive($query)
    {
        return $query->where('status', WorkspaceStatus::ACTIVE);
    }

    /**
     * Scope a query to only include archived workspaces.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', WorkspaceStatus::ARCHIVED);
    }

    /**
     * Check if workspace is active.
     */
    public function isActive(): bool
    {
        return $this->status === WorkspaceStatus::ACTIVE;
    }

    /**
     * Check if workspace is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === WorkspaceStatus::ARCHIVED;
    }

    /**
     * Archive the workspace.
     */
    public function archive(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ARCHIVED]);
    }

    /**
     * Activate the workspace.
     */
    public function activate(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ACTIVE]);
    }
}
