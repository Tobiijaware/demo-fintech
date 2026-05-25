<?php

namespace App\Enums;

enum AmlCaseStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Escalated = 'escalated';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under review',
            self::Escalated => 'Escalated',
            self::Closed => 'Closed',
        };
    }
}
