<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
