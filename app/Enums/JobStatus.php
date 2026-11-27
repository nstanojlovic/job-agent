<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JobStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Saved = 'saved';
    case Applied = 'applied';
    case EmailSent = 'email_sent';
    case LinkedInMessage = 'linkedin_message';
    case Interview = 'interview';
    case Rejected = 'rejected';
    case Skipped = 'skipped';

    /** Rejected automatically by the screener. */
    case Hidden = 'hidden';

    public function getLabel(): string
    {
        return match ($this) {
            self::EmailSent => 'Email sent',
            self::LinkedInMessage => 'LinkedIn msg',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Saved => 'warning',
            self::Applied, self::EmailSent, self::LinkedInMessage => 'primary',
            self::Interview => 'success',
            self::Rejected => 'danger',
            self::Skipped, self::Hidden => 'gray',
        };
    }

    /**
     * Statuses where I've reached out (or plan to) and am waiting to hear back.
     *
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Saved, self::Applied, self::EmailSent, self::LinkedInMessage, self::Interview];
    }

    /**
     * Statuses that mean "done with this one".
     *
     * @return list<self>
     */
    public static function closed(): array
    {
        return [self::Rejected, self::Skipped];
    }

    /**
     * Map a status as written in the spreadsheet tracker, e.g. "Email sent (open?)".
     */
    public static function fromTracker(string $value): self
    {
        $value = mb_strtolower(trim($value));

        return match (true) {
            str_starts_with($value, 'email') => self::EmailSent,
            str_starts_with($value, 'linkedin') => self::LinkedInMessage,
            default => collect(self::cases())
                ->first(fn (self $case) => mb_strtolower($case->getLabel()) === $value) ?? self::New,
        };
    }
}
