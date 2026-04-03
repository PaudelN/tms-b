<?php

namespace App\Models;

use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasMedia,Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'extra',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'extra' => 'array',
    ];

    /** Workspaces the user owns */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'user_id');
    }

    /** Workspaces user is a member of (pivot) */
    public function joinedWorkspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_users')
            ->withPivot('is_owner', 'status', 'invite_token')
            ->withTimestamps();
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

    // social accounts linked to user
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Returns the avatar URL from any linked social account,
     * falling back to null if none exist.
     */
    public function getSocialAvatarAttribute(): ?string
    {
        return $this->socialAccounts()->whereNotNull('avatar')->value('avatar');
    }
}
