<?php

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
