<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\Paginatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use Filterable, Paginatable;

    // ── Aggregate-type constants ──────────────────────────────────────────────
    const TYPE_IMAGE = 'image';

    const TYPE_VIDEO = 'video';

    const TYPE_AUDIO = 'audio';

    const TYPE_DOCUMENT = 'document';

    const TYPE_OTHER = 'other';

    // ── MIME → aggregate-type map ─────────────────────────────────────────────
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
    const ALLOWED_MIMES = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'mp4', 'mov', 'avi', 'webm',
        'mp3', 'wav', 'ogg', 'aac',
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

    // url, path, human_size are computed — appended to every serialisation
    protected $appends = ['url', 'path', 'human_size'];

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Relative storage path: "uploads/tasks/42/photo.jpg"
     */
    public function getPathAttribute(): string
    {
        $dir = $this->directory ? rtrim($this->directory, '/').'/' : '';

        return $dir.$this->filename.'.'.$this->extension;
    }

    /**
     * Public URL via the configured disk.
     */
    public function getUrlAttribute(): string
    {
        return asset(Storage::disk($this->disk)->url($this->path));
    }

    /**
     * Human-readable file size (e.g. "1.4 MB", "320 KB").
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filter by aggregate type (image, video, audio, document, other).
     *
     *   Media::query()->ofType('image')->get();
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('aggregate_type', $type);
    }

    /**
     * Filter by the uploading user.
     *
     *   Media::query()->uploadedBy($userId)->get();
     */
    public function scopeUploadedBy($query, int $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Order by newest first (default for the global library).
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Derive the aggregate type from a MIME type string.
     *
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
     *   Media::directory('tasks')               → "uploads/tasks"
     *   Media::directory('tasks', 42)           → "uploads/tasks/42"
     *   Media::directory('users', 7, 'avatars') → "uploads/users/7/avatars"
     */
    public static function directory(
        string $context,
        ?int $id = null,
        ?string $tag = null,
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
