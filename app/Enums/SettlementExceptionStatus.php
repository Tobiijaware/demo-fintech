<?php

namespace App\Enums;

enum SettlementExceptionStatus: string
{
    case Open = 'open';
    case InInvestigation = 'in_investigation';
    case PendingPartner = 'pending_partner';
    case Resolved = 'resolved';
}
