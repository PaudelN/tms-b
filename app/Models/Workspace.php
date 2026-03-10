<?php

namespace App\Models;

use App\Contracts\KanbanEntity;
use App\Enums\WorkspaceStatus;
use App\Traits\Filterable;
use App\Traits\HasKanban;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Workspace extends Model implements KanbanEntity
{
    // Filterable added — everything else is untouched.
    use Filterable, HasFactory, HasKanban, HasSlug, Paginatable, SoftDeletes;

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

    /**
     * The column that holds the kanban stage value.
     * For Workspace this is the 'status' enum column.
     *
     * For future entities this will differ:
     *   Task  → 'stage'                (its own pipeline_stage enum)
     *   Deal  → 'pipeline_stage_id'    (FK to a pipeline_stages table)
     */
    public function kanbanColumnField(): string
    {
        return 'status';
    }

    /**
     * Guard: prevent moving if the workspace is somehow locked.
     * Extend this for business rules (e.g. prevent moving to archived if has active tasks).
     */
    public function kanbanCanMove(mixed $newStageValue): bool
    {
        return true;
    }

    /**
     * Side effects after a successful stage change.
     * Good place to fire events for real-time updates, webhooks, audit logs.
     */
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
