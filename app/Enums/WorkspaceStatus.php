<?php

namespace App\Enums;

enum WorkspaceStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case PENDING = 'pending';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ARCHIVED => 'Archived',
            self::PENDING => 'Pending',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACTIVE => 'Fully operational and visible to all members.',
            self::PENDING => 'Awaiting setup or approval before going live.',
            self::ON_HOLD => 'Temporarily paused. Accessible but not in active use.',
            self::COMPLETED => 'Work finished. Kept for reference.',
            self::ARCHIVED => 'Read-only. Preserved for historical reference.',
        };
    }

    /**
     * Actual hex color — used by the frontend for:
     *   - Kanban card top-stripe (the 3D colored band)
     *   - Column header dot glow
     *   - Stage pill background tints
     *
     * These match the Tailwind utility colors used in dotClass() / badgeClass()
     * but as real hex so the frontend can use them in inline styles.
     */
    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => '#10b981', // emerald-500
            self::PENDING => '#fbbf24', // amber-400
            self::ON_HOLD => '#f87171', // red-400
            self::COMPLETED => '#60a5fa', // blue-400
            self::ARCHIVED => '#fb7185', // rose-400
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-emerald-500',
            self::PENDING => 'bg-amber-400',
            self::ON_HOLD => 'bg-red-400',
            self::COMPLETED => 'bg-blue-400',
            self::ARCHIVED => 'bg-rose-400',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::PENDING => 'bg-amber-50 text-amber-700 border-amber-200',
            self::ON_HOLD => 'bg-orange-50 text-orange-700 border-orange-200',
            self::COMPLETED => 'bg-blue-50 text-blue-700 border-blue-200',
            self::ARCHIVED => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Full serialized array — returned by GET /enums/workspace-statuses.
     * The frontend stores this as "stages" for UiKanban column definitions.
     * All fields (value, label, description, color, dot, badge) are included
     * so the kanban board has everything it needs without extra API calls.
     */
    public static function toArray(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
            'color' => $case->color(),    // hex — for card stripe + column dot glow
            'dot' => $case->dotClass(), // tailwind — for dot indicators
            'badge' => $case->badgeClass(), // tailwind — for badge pill
        ], self::cases());
    }
}
