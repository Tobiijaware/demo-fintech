<?php

namespace App\Enums;

enum AgentCommissionStatus: string
{
    case Accrued = 'accrued';
    case Paid = 'paid';
}
