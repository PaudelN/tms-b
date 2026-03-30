<?php

namespace App\Models;

use App\Contracts\KanbanEntity;
use App\Enums\WorkspaceStatus;
use App\Traits\Filterable;
use App\Traits\HasKanban;
use App\Traits\HasMedia;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Workspace extends Model implements KanbanEntity
{
    use Filterable, HasFactory, HasKanban,HasMedia, HasSlug, Paginatable, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'user_id',
        'status',
        'extra',
    ];

    protected $casts = [
        'status' => WorkspaceStatus::class,
        'extra' => 'array',
    ];

    // ── KanbanEntity contract ─────────────────────────────────────────────────

    public function kanbanColumnField(): string
    {
        return 'status';
    }

    public function kanbanCanMove(mixed $newStageValue): bool
    {
        return true;
    }

    public function kanbanAfterMove(string $field, mixed $newStageValue): void
    {
        // Example: WorkspaceStageChanged::dispatch($this, $newStageValue);
    }

    public function kanbanBeforeMove(mixed $newStageValue): void
    {
        //
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * All projects that belong to this workspace.
     * Ordered by created_at desc so latest appear first in workspace detail.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('created_at', 'desc');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->withPivot('is_owner', 'status', 'invite_token')
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function pendingMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'pending');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function inviteMember(?int $userId = null): string
    {
        $token = (string) Str::uuid();

        $this->members()->attach($userId, [
            'is_owner' => false,
            'status' => 'pending',
            'invite_token' => $token,
        ]);

        return url("/workspace/join/{$token}");
    }

    public function acceptInvitation(string $token, int $userId): void
    {
        $this->members()
            ->wherePivot('status', 'pending')
            ->wherePivot('invite_token', $token)
            ->firstOrFail();

        $this->members()->updateExistingPivot($userId, [
            'user_id' => $userId,
            'status' => 'active',
            'invite_token' => null,
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', WorkspaceStatus::ACTIVE);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', WorkspaceStatus::ARCHIVED);
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === WorkspaceStatus::ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === WorkspaceStatus::ARCHIVED;
    }

    public function archive(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ARCHIVED]);
    }

    public function activate(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ACTIVE]);
    }
}
