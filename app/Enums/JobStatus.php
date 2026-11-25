<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JobStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Saved = 'saved';
    case Applied = 'applied';
    case Interview = 'interview';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Saved => 'warning',
            self::Applied => 'primary',
            self::Interview => 'success',
            self::Rejected => 'danger',
            self::Hidden => 'gray',
        };
    }
}
