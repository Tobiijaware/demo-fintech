<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Pending = 'pending';
}
