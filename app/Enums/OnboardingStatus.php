<?php

namespace App\Enums;

enum OnboardingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingReview = 'pending_review';
    case Queried = 'queried';
    case OnHold = 'on_hold';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReVerification = 're_verification';

    /** @return list<self> */
    public static function queueStatuses(): array
    {
        return [
            self::Submitted,
            self::PendingReview,
            self::Queried,
            self::OnHold,
            self::ReVerification,
        ];
    }
}
