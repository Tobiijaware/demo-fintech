<?php

namespace App\Enums;

enum SettlementExceptionCategory: string
{
    case FailedCredit = 'failed_credit';
    case Duplicate = 'duplicate';
    case Unmatched = 'unmatched';
    case Pending = 'pending';
}
