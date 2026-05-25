<?php

namespace App\Enums;

enum ReversalStatus: string
{
    case PendingApproval = 'Pending approval';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Processed = 'Processed';
}
