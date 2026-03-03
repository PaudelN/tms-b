<?php

namespace App\Enums;

enum WorkspaceStatus: string
{
    case ACTIVE    = 'active';
    case ARCHIVED  = 'archived';
    case PENDING   = 'pending';
    case ON_HOLD   = 'on_hold';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE    => 'Active',
            self::ARCHIVED  => 'Archived',
            self::PENDING   => 'Pending',
            self::ON_HOLD   => 'On Hold',
            self::COMPLETED => 'Completed',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::ACTIVE    => 'Fully operational and visible to all members.',
            self::PENDING   => 'Awaiting setup or approval before going live.',
            self::ON_HOLD   => 'Temporarily paused. Accessible but not in active use.',
            self::COMPLETED => 'Work finished. Kept for reference.',
            self::ARCHIVED  => 'Read-only. Preserved for historical reference.',
        };
    }

    public function dotClass(): string
    {
        return match($this) {
            self::ACTIVE    => 'bg-emerald-500',
            self::PENDING   => 'bg-amber-400',
            self::ON_HOLD   => 'bg-red-400',
            self::COMPLETED => 'bg-blue-400',
            self::ARCHIVED  => 'bg-rose-400',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::ACTIVE    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::PENDING   => 'bg-amber-50 text-amber-700 border-amber-200',
            self::ON_HOLD   => 'bg-orange-50 text-orange-700 border-orange-200',
            self::COMPLETED => 'bg-blue-50 text-blue-700 border-blue-200',
            self::ARCHIVED  => 'bg-rose-50 text-rose-700 border-rose-200',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value'       => $case->value,
            'label'       => $case->label(),
            'description' => $case->description(),
            'dot'         => $case->dotClass(),
            'badge'       => $case->badgeClass(),
        ], self::cases());
    }
}
