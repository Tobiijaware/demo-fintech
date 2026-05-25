<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Current = 'current';
    case ReviewDue = 'review_due';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::ReviewDue => 'Review due',
            self::Archived => 'Archived',
        };
    }
}
