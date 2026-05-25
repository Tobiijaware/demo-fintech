<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Active = 'active';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
}
