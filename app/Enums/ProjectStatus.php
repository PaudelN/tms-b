<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case DRAFT       = 'draft';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD     = 'on_hold';
    case CANCELLED   = 'cancelled';
    case COMPLETED   = 'completed';

    public function label(): string
    {
        return match($this) {
            self::DRAFT       => 'Draft',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD     => 'On Hold',
            self::CANCELLED   => 'Cancelled',
            self::COMPLETED   => 'Completed',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::DRAFT       => 'Project is being set up. Not yet active.',
            self::IN_PROGRESS => 'Actively being worked on by the team.',
            self::ON_HOLD     => 'Temporarily paused. Accessible but not in active use.',
            self::CANCELLED   => 'Project has been cancelled and will not continue.',
            self::COMPLETED   => 'Work finished. Kept for reference.',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT       => '#94a3b8', // slate-400
            self::IN_PROGRESS => '#10b981', // emerald-500
            self::ON_HOLD     => '#f87171', // red-400
            self::CANCELLED   => '#fb7185', // rose-400
            self::COMPLETED   => '#60a5fa', // blue-400
        };
    }

    public function dotClass(): string
    {
        return match($this) {
            self::DRAFT       => 'bg-slate-400',
            self::IN_PROGRESS => 'bg-emerald-500',
            self::ON_HOLD     => 'bg-red-400',
            self::CANCELLED   => 'bg-rose-400',
            self::COMPLETED   => 'bg-blue-400',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::DRAFT       => 'bg-slate-50 text-slate-700 border-slate-200',
            self::IN_PROGRESS => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::ON_HOLD     => 'bg-orange-50 text-orange-700 border-orange-200',
            self::CANCELLED   => 'bg-rose-50 text-rose-700 border-rose-200',
            self::COMPLETED   => 'bg-blue-50 text-blue-700 border-blue-200',
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
            'color'       => $case->color(),
            'dot'         => $case->dotClass(),
            'badge'       => $case->badgeClass(),
        ], self::cases());
    }
}
