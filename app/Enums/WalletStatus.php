<?php

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Pnd = 'pnd';
    case Frozen = 'frozen';
    case Closed = 'closed';
}
