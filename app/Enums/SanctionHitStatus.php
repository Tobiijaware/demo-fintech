<?php

namespace App\Enums;

enum SanctionHitStatus: string
{
    case PendingReview = 'pending_review';
    case FalsePositive = 'false_positive';
    case ConfirmedMatch = 'confirmed_match';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending review',
            self::FalsePositive => 'False positive',
            self::ConfirmedMatch => 'Confirmed match',
        };
    }
}
