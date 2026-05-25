<?php

namespace App\Enums;

enum TicketPriority: string
{
    case High = 'High';
    case Normal = 'Normal';
    case Low = 'Low';
}
