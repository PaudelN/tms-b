<?php

namespace App\Models;

use App\Enums\WorkspaceStatus;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Illuminate\Support\Str;

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

    /** Slug configuration */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->preventOverwrite();
    }

    /** Owner of the workspace */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Members of the workspace (pivot table) */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
                    ->withPivot('is_owner', 'status', 'invite_token')
                    ->withTimestamps();
    }

    /** Only active members */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    /** Only pending members (invited but not joined yet) */
    public function pendingMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'pending');
    }

    /** Invite a member (copy-paste link) */
    public function inviteMember(?int $userId = null): string
    {
        $token = (string) Str::uuid();

        $this->members()->attach($userId, [
            'is_owner' => false,
            'status' => 'pending',
            'invite_token' => $token
        ]);

        return url("/workspace/join/{$token}");
    }

    /** Accept an invitation using token */
    public function acceptInvitation(string $token, int $userId): void
    {
        $pivot = $this->members()
                      ->wherePivot('status', 'pending')
                      ->wherePivot('invite_token', $token)
                      ->firstOrFail();

        $this->members()->updateExistingPivot($userId, [
            'user_id' => $userId,
            'status' => 'active',
            'invite_token' => null
        ]);
    }

    /** Scope for active workspaces */
    public function scopeActive($query)
    {
        return $query->where('status', WorkspaceStatus::ACTIVE);
    }

    /** Scope for archived workspaces */
    public function scopeArchived($query)
    {
        return $query->where('status', WorkspaceStatus::ARCHIVED);
    }

    /** Check if workspace is active */
    public function isActive(): bool
    {
        return $this->status === WorkspaceStatus::ACTIVE;
    }

    /** Check if workspace is archived */
    public function isArchived(): bool
    {
        return $this->status === WorkspaceStatus::ARCHIVED;
    }

    /** Archive the workspace */
    public function archive(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ARCHIVED]);
    }

    /** Activate the workspace */
    public function activate(): bool
    {
        return $this->update(['status' => WorkspaceStatus::ACTIVE]);
    }
}
