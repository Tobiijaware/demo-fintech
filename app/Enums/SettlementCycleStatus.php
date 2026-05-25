<?php

namespace App\Enums;

enum SettlementCycleStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Settled = 'settled';
}
