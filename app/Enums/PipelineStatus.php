<?php

namespace App\Enums;

enum PipelineStatus: int
{
    case ACTIVE   = 1;
    case INACTIVE = 0;

    // ── Display helpers ───────────────────────────────────────────────────────

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
            self::ACTIVE   => 'This pipeline is active and available for use.',
            self::INACTIVE => 'This pipeline is inactive and hidden from task flows.',
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
            self::INACTIVE => 'badge-ghost',
        };
    }

    // ── Serialisation ─────────────────────────────────────────────────────────

    /**
     * Returns all cases as an array suitable for API enum endpoints.
     * Shape: [['value' => 1, 'label' => 'Active', ...], ...]
     */
    public static function toArray(): array
    {
        return array_map(
            fn (self $case) => [
                'value'       => $case->value,
                'label'       => $case->label(),
                'description' => $case->description(),
                'color'       => $case->color(),
                'dot'         => $case->dotClass(),
                'badge'       => $case->badgeClass(),
            ],
            self::cases()
        );
    }
}
