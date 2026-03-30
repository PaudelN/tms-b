<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * MediaService
 *
 * Central place for all file operations. Controllers and traits
 * delegate here — nothing touches Storage or Media directly.
 */
class MediaService
{
    /**
     * Store an uploaded file and create its Media record.
     *
     * @param  UploadedFile  $file  The uploaded file from the request
     * @param  string  $directory  Target directory on the disk
     * @param  string  $disk  Filesystem disk (default: 'public')
     * @param  string|null  $alt  Optional alt / caption text
     */
    public function store(
        UploadedFile $file,
        string $directory = 'uploads',
        string $disk = 'public',
        ?string $alt = null,
    ): Media {
        $filename = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?? $file->getClientMimeType();

        Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            "{$filename}.{$extension}",
        );

        return Media::create([
            'disk' => $disk,
            'directory' => $directory,
            'filename' => $filename,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'aggregate_type' => Media::aggregateTypeFor($mimeType),
            'size' => $file->getSize(),
            'original_filename' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt' => $alt,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /**
     * Delete a Media record and its physical file.
     * Safe to call even if the file no longer exists on disk.
     */
    public function delete(Media $media): void
    {
        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();
    }

    /**
     * Upload, create the Media record, AND attach it to a model in one step.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model  Any model using HasMedia
     */
    public function storeAndAttach(
        UploadedFile $file,
        \Illuminate\Database\Eloquent\Model $model,
        string $tag = 'default',
        string $disk = 'public',
        ?string $alt = null,
    ): Media {
        $morphType = strtolower(class_basename($model));
        $directory = Media::directory($morphType, $model->getKey(), $tag);

        $media = $this->store(
            file: $file,
            directory: $directory,
            disk: $disk,
            alt: $alt,
        );

        $model->attachMedia($media, $tag);

        return $media;
    }

    /**
     * Update mutable metadata fields on an existing Media record.
     * The physical file is NOT touched.
     *
     * FIX: The original used array_filter(..., fn($v) => $v !== null) which
     * silently dropped intentional null values (clearing alt).
     * We now only update keys that were explicitly passed in $data.
     *
     * @param  array{alt?: string|null}  $data
     */
    public function update(Media $media, array $data): Media
    {
        // Only touch fields that are explicitly present in $data —
        // this allows callers to pass null to clear a field.
        $payload = [];

        if (array_key_exists('alt', $data)) {
            $payload['alt'] = $data['alt'];
        }

        if (! empty($payload)) {
            $media->update($payload);
        }

        return $media->fresh();
    }

    /**
     * Resolve a polymorphic model string to its Eloquent class.
     *
     *   "tasks"      → App\Models\Task
     *   "users"      → App\Models\User
     *   "projects"   → App\Models\Project
     *   "pipelines"  → App\Models\Pipeline
     *   "workspaces" → App\Models\Workspace
     */
    public function resolveModel(string $morphType): ?string
    {
        $map = [
            'tasks' => \App\Models\Task::class,
            'users' => \App\Models\User::class,
            'projects' => \App\Models\Project::class,
            'pipelines' => \App\Models\Pipeline::class,
            'workspaces' => \App\Models\Workspace::class,
        ];

        return $map[$morphType] ?? null;
    }
}
