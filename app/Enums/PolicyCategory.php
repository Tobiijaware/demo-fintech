<?php

namespace App\Enums;

enum PolicyCategory: string
{
    case Onboarding = 'onboarding';
    case Agents = 'agents';
    case Aml = 'aml';
    case Security = 'security';
    case Reporting = 'reporting';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Onboarding => 'Onboarding',
            self::Agents => 'Agents',
            self::Aml => 'AML',
            self::Security => 'Security',
            self::Reporting => 'Reporting',
            self::Support => 'Support',
        };
    }
}
