<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    // ── Aggregate-type constants ──────────────────────────────────────────────
    // Use these everywhere instead of raw strings so a typo is a compile error.

    const TYPE_IMAGE = 'image';

    const TYPE_VIDEO = 'video';

    const TYPE_AUDIO = 'audio';

    const TYPE_DOCUMENT = 'document';

    const TYPE_OTHER = 'other';

    // ── MIME → aggregate-type map ─────────────────────────────────────────────
    // Extend this list as you support more formats.

    const AGGREGATE_MAP = [
        self::TYPE_IMAGE => [
            'image/jpeg', 'image/png', 'image/gif',
            'image/webp', 'image/svg+xml', 'image/bmp',
        ],
        self::TYPE_VIDEO => [
            'video/mp4', 'video/mpeg', 'video/quicktime',
            'video/webm', 'video/x-msvideo',
        ],
        self::TYPE_AUDIO => [
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'audio/mp4', 'audio/aac',
        ],
        self::TYPE_DOCUMENT => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ],
    ];

    // ── Allowed upload MIME types ─────────────────────────────────────────────
    // Flat list used by the StoreRequest validation rule.

    const ALLOWED_MIMES = [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        // Video
        'mp4', 'mov', 'avi', 'webm',
        // Audio
        'mp3', 'wav', 'ogg', 'aac',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
    ];

    // ── Max upload size (bytes) ───────────────────────────────────────────────
    const MAX_SIZE = 20 * 1024 * 1024; // 20 MB

    // ─────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        'disk',
        'directory',
        'filename',
        'extension',
        'mime_type',
        'aggregate_type',
        'size',
        'original_filename',
        'alt',
        'uploaded_by',
    ];

    protected $appends = ['url', 'path'];

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Relative storage path:  "uploads/tasks/photo.jpg"
     */
    public function getPathAttribute(): string
    {
        $dir = $this->directory ? rtrim($this->directory, '/').'/' : '';

        return $dir.$this->filename.'.'.$this->extension;
    }

    /**
     * Public URL via the configured disk.
     * Works for local (public) disk and S3 alike.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Derive the aggregate type from a MIME type string.
     *
     * Usage:
     *   Media::aggregateTypeFor('image/jpeg')  → 'image'
     *   Media::aggregateTypeFor('text/html')   → 'other'
     */
    public static function aggregateTypeFor(string $mimeType): string
    {
        foreach (self::AGGREGATE_MAP as $type => $mimes) {
            if (in_array($mimeType, $mimes, true)) {
                return $type;
            }
        }

        return self::TYPE_OTHER;
    }

    /**
     * Build the storage directory for a given context.
     *
     * Examples:
     *   Media::directory('tasks')           → "uploads/tasks"
     *   Media::directory('tasks', 42)       → "uploads/tasks/42"
     *   Media::directory('users', 7, 'avatars') → "uploads/users/7/avatars"
     */
    public static function directory(
        string $context,
        ?int $id = null,
        ?string $tag = null
    ): string {
        $parts = ['uploads', $context];

        if ($id !== null) {
            $parts[] = (string) $id;
        }

        if ($tag !== null && $tag !== 'default') {
            $parts[] = $tag;
        }

        return implode('/', $parts);
    }
}
