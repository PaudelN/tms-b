<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * HasMedia — give any Eloquent model full media capabilities.
 *
 * ─── Setup ────────────────────────────────────────────────────────────────
 *
 *   class Task extends Model {
 *       use HasMedia;
 *   }
 *
 * ─── Usage ────────────────────────────────────────────────────────────────
 *
 *   // Attach (idempotent — won't duplicate)
 *   $task->attachMedia($mediaId, 'attachments');
 *
 *   // Detach one
 *   $task->detachMedia($mediaId, 'attachments');
 *
 *   // Detach all from a tag
 *   $task->clearMedia('attachments');
 *
 *   // Replace all media in a tag with a new set (ordered)
 *   $task->syncMedia([1, 2, 3], 'attachments');
 *
 *   // Query
 *   $task->getMedia();                   // all tags
 *   $task->getMedia('attachments');      // one tag
 *   $task->getFirstMedia('avatar');      // first or null
 *   $task->hasMedia('attachments');      // bool
 *
 *   // Eager-load in a query
 *   Task::with('media')->get();
 *   Task::with('attachments')->get();    // scoped relationship
 */
trait HasMedia
{
    // ── Core relationship ─────────────────────────────────────────────────────

    /**
     * All media attached to this model (all tags).
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['tag', 'order'])
            ->orderBy('mediables.order');
    }

    // ── Named-tag relationship helpers ────────────────────────────────────────
    // Define these per model when you want typed eager-loading:
    //
    //   public function attachments(): MorphToMany
    //   {
    //       return $this->mediaByTag('attachments');
    //   }
    //
    // Then you can do Task::with('attachments')->get()

    /**
     * Scoped relationship for a specific tag.
     * Override or call from named accessors on your model.
     */
    public function mediaByTag(string $tag): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable')
            ->withPivot(['tag', 'order'])
            ->wherePivot('tag', $tag)
            ->orderBy('mediables.order');
    }

    // ── Query helpers ─────────────────────────────────────────────────────────

    /**
     * Get all media, optionally filtered by tag.
     */
    public function getMedia(?string $tag = null): Collection
    {
        if ($tag === null) {
            return $this->media()->get();
        }

        return $this->mediaByTag($tag)->get();
    }

    /**
     * Get the first media item for a tag (or null).
     */
    public function getFirstMedia(string $tag = 'default'): ?Media
    {
        return $this->mediaByTag($tag)->first();
    }

    /**
     * Get the URL of the first media item for a tag (or null).
     * Useful in API resources: $task->getFirstMediaUrl('cover')
     */
    public function getFirstMediaUrl(string $tag = 'default'): ?string
    {
        return $this->getFirstMedia($tag)?->url;
    }

    /**
     * Check whether any media exists for the given tag (or any tag).
     */
    public function hasMedia(?string $tag = null): bool
    {
        return $this->getMedia($tag)->isNotEmpty();
    }

    // ── Mutation helpers ──────────────────────────────────────────────────────

    /**
     * Attach a media item to this model under a tag.
     *
     * Idempotent: calling this twice with the same arguments is safe
     * because the composite PK on mediables prevents duplicates.
     * Laravel will throw a QueryException on duplicate — we catch it silently.
     *
     * @param  string  $tag  named slot, e.g. "attachments", "avatar"
     * @param  int|null  $order  explicit position (auto-increments if null)
     */
    public function attachMedia(int|Media $media, string $tag = 'default', ?int $order = null): void
    {
        $mediaId = $media instanceof Media ? $media->id : $media;

        if ($order === null) {
            // Auto-calculate order: put at the end of the current tag list
            $order = $this->mediaByTag($tag)->count();
        }

        try {
            $this->media()->attach($mediaId, [
                'tag' => $tag,
                'order' => $order,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate attach — composite PK violation. Silently ignore.
            // If you want to update the order instead, call syncMedia().
        }
    }

    /**
     * Detach a single media item from this model under a tag.
     */
    public function detachMedia(int|Media $media, string $tag = 'default'): void
    {
        $mediaId = $media instanceof Media ? $media->id : $media;

        \DB::table('mediables')
            ->where('media_id', $mediaId)
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey())
            ->where('tag', $tag)
            ->delete();
    }

    /**
     * Remove all media from a specific tag (or all tags).
     */
    public function clearMedia(?string $tag = null): void
    {
        $query = \DB::table('mediables')
            ->where('mediable_type', $this->getMorphClass())
            ->where('mediable_id', $this->getKey());

        if ($tag !== null) {
            $query->where('tag', $tag);
        }

        $query->delete();
    }

    /**
     * Replace the entire media set for a tag with a new ordered list.
     *
     * $task->syncMedia([3, 1, 2], 'attachments');
     *   → removes old attachments, attaches 3, 1, 2 in that order
     *
     * @param  array<int|Media>  $mediaItems
     */
    public function syncMedia(array $mediaItems, string $tag = 'default'): void
    {
        // 1. Clear existing
        $this->clearMedia($tag);

        // 2. Re-attach in given order
        foreach ($mediaItems as $order => $item) {
            $this->attachMedia($item, $tag, $order);
        }
    }

    /**
     * Update the sort order for all items in a tag.
     * Pass an array of media IDs in the desired order.
     *
     * @param  array<int>  $orderedIds
     */
    public function reorderMedia(array $orderedIds, string $tag = 'default'): void
    {
        foreach ($orderedIds as $position => $mediaId) {
            \DB::table('mediables')
                ->where('media_id', $mediaId)
                ->where('mediable_type', $this->getMorphClass())
                ->where('mediable_id', $this->getKey())
                ->where('tag', $tag)
                ->update(['order' => $position]);
        }
    }
}
