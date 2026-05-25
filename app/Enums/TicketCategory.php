<?php

namespace App\Enums;

enum TicketCategory: string
{
    case FailedTxn = 'Failed txn';
    case Lockout = 'Lockout';
    case Reversal = 'Reversal';
    case Dispute = 'Dispute';
    case Account = 'Account';
    case General = 'General';
    case PinReset = 'PIN reset';
    case Enquiry = 'Enquiry';
}
