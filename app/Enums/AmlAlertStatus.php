<?php

namespace App\Enums;

enum AmlAlertStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Escalated = 'escalated';
    case Closed = 'closed';
}
