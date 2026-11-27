<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * High = WordPress or Laravel focus at the right level and location,
 * Medium = good but with a gap, Low = a stretch.
 */
enum JobFit: string implements HasColor, HasLabel
{
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::High => 'success',
            self::Medium => 'warning',
            self::Low => 'gray',
        };
    }

    /** Lower sorts first. */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }
}
