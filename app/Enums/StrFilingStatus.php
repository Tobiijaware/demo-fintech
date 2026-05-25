<?php

namespace App\Enums;

enum StrFilingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending review',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
        };
    }
}
