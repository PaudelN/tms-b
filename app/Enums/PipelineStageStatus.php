<?php

namespace App\Enums;

enum PipelineStageStatus: int
{
    case ACTIVE   = 1;
    case INACTIVE = 0;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACTIVE   => 'Stage is visible and accepting tasks.',
            self::INACTIVE => 'Stage is hidden from the pipeline board.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE   => 'green',
            self::INACTIVE => 'gray',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::ACTIVE   => 'bg-green-500',
            self::INACTIVE => 'bg-gray-400',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE   => 'badge-success',
            self::INACTIVE => 'badge-secondary',
        };
    }

    public static function toArray(): array
    {
        return array_map(fn ($case) => [
            'value'       => $case->value,
            'label'       => $case->label(),
            'description' => $case->description(),
            'color'       => $case->color(),
        ], self::cases());
    }
}
