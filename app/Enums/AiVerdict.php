<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AiVerdict: string implements HasColor, HasLabel
{
    case Match = 'match';
    case Agency = 'agency';
    case Language = 'language';
    case Location = 'location';
    case Irrelevant = 'irrelevant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Match => 'Match',
            self::Agency => 'Agency / staffing',
            self::Language => 'Language requirement',
            self::Location => 'Location not eligible',
            self::Irrelevant => 'Not relevant',
        };
    }

    public function getColor(): string
    {
        return $this === self::Match ? 'success' : 'gray';
    }
}
