<?php

namespace App\Enums;

enum TaskPriority: string
{
    case LOW      = 'low';
    case MEDIUM   = 'medium';
    case HIGH     = 'high';
    case CRITICAL = 'critical';

    public function label(): string
    {
        return match($this) {
            self::LOW      => 'Low',
            self::MEDIUM   => 'Medium',
            self::HIGH     => 'High',
            self::CRITICAL => 'Critical',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::LOW      => '#34c789',
            self::MEDIUM   => '#f6a623',
            self::HIGH     => '#ff6b3d',
            self::CRITICAL => '#e53e3e',
        };
    }

    public function dot(): string
    {
        return match($this) {
            self::LOW      => 'bg-emerald-500',
            self::MEDIUM   => 'bg-amber-500',
            self::HIGH     => 'bg-orange-500',
            self::CRITICAL => 'bg-red-600',
        };
    }

    public function badge(): string
    {
        return match($this) {
            self::LOW      => 'bg-emerald-500/10 text-emerald-700 border-emerald-500/30',
            self::MEDIUM   => 'bg-amber-500/10 text-amber-700 border-amber-500/30',
            self::HIGH     => 'bg-orange-500/10 text-orange-700 border-orange-500/30',
            self::CRITICAL => 'bg-red-500/10 text-red-700 border-red-500/30',
        };
    }

    public static function toArray(): array
    {
        return array_map(fn (self $case) => [
            'value'       => $case->value,
            'label'       => $case->label(),
            'color'       => $case->color(),
            'dot'         => $case->dot(),
            'badge'       => $case->badge(),
        ], self::cases());
    }
}
