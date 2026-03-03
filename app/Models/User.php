<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'extra'
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
}
