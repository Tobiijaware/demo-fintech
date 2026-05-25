<?php

namespace App\Enums;

enum AmlSubjectType: string
{
    case Agent = 'agent';
    case Customer = 'customer';
    case Wallet = 'wallet';
    case Merchant = 'merchant';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Agent',
            self::Customer => 'Customer',
            self::Wallet => 'Wallet',
            self::Merchant => 'Merchant',
        };
    }
}
