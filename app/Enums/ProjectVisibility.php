<?php

namespace App\Enums;

enum ProjectVisibility: string
{
    case PRIVATE   = 'private';
    case WORKSPACE = 'workspace';
    case PUBLIC    = 'public';

    public function label(): string
    {
        return match ($this) {
            self::PRIVATE   => 'Private',
            self::WORKSPACE => 'Workspace',
            self::PUBLIC    => 'Public',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PRIVATE   => 'Only project members can see this project.',
            self::WORKSPACE => 'All workspace members can see this project.',
            self::PUBLIC    => 'Anyone with the link can view this project.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PRIVATE   => '#f87171', // red-400
            self::WORKSPACE => '#fbbf24', // amber-400
            self::PUBLIC    => '#10b981', // emerald-500
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::PRIVATE   => 'bg-red-400',
            self::WORKSPACE => 'bg-amber-400',
            self::PUBLIC    => 'bg-emerald-500',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PRIVATE   => 'bg-red-50 text-red-700 border-red-200',
            self::WORKSPACE => 'bg-amber-50 text-amber-700 border-amber-200',
            self::PUBLIC    => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => [
            'value'       => $case->value,
            'label'       => $case->label(),
            'description' => $case->description(),
            'color'       => $case->color(),
            'dot'         => $case->dotClass(),
            'badge'       => $case->badgeClass(),
        ], self::cases());
    }
}
