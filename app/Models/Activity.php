<?php

namespace App\Models;

use App\Enums\ActivityEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property int|null $causer_id
 * @property string $event
 * @property array|null $properties
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Model       $subject
 * @property-read User|null   $causer
 */
class Activity extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'causer_id',
        'event',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForEvent(Builder $query, string|ActivityEvent $event): Builder
    {
        $value = $event instanceof ActivityEvent ? $event->value : $event;

        return $query->where('event', $value);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        $values = collect(ActivityEvent::cases())
            ->filter(fn (ActivityEvent $e) => $e->category() === $category)
            ->map(fn (ActivityEvent $e) => $e->value)
            ->values()
            ->all();

        return $query->whereIn('event', $values);
    }

    public function scopeCausedBy(Builder $query, int $userId): Builder
    {
        return $query->where('causer_id', $userId);
    }

    public function scopeWithCauser(Builder $query): Builder
    {
        return $query->with('causer:id,name');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function eventEnum(): ?ActivityEvent
    {
        return ActivityEvent::tryFrom($this->event);
    }

    public function oldValue(string $field): mixed
    {
        return data_get($this->properties, "changes.{$field}.old");
    }

    public function newValue(string $field): mixed
    {
        return data_get($this->properties, "changes.{$field}.new");
    }

    public function hasChange(string $field): bool
    {
        return array_key_exists($field, data_get($this->properties, 'changes', []));
    }

    public function changedFields(): array
    {
        return array_keys(data_get($this->properties, 'changes', []));
    }
}
