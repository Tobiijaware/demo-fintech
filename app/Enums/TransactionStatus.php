<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Success = 'success';
    case Pending = 'pending';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
