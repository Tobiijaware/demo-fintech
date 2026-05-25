<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'Open';
    case Pending = 'Pending';
    case Resolved = 'Resolved';
    case Escalated = 'Escalated';
    case InReview = 'In review';
    case AwaitingCustomer = 'Awaiting customer';
}
