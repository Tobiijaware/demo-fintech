<?php

namespace App\Enums;

enum FilingStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Overdue => 'Overdue',
        };
    }
}
