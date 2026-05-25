<?php

namespace App\Enums;

enum FindingStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Remediated = 'remediated';
    case Accepted = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Remediated => 'Remediated',
            self::Accepted => 'Accepted',
        };
    }
}
