<?php

namespace App\Enums;

enum AccountTier: string
{
    case Tier1 = 'tier_1';
    case Tier2 = 'tier_2';
    case Tier3 = 'tier_3';

    public function label(): string
    {
        return match ($this) {
            self::Tier1 => 'Tier 1',
            self::Tier2 => 'Tier 2',
            self::Tier3 => 'Tier 3 — Super agent',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Tier1 => 1,
            self::Tier2 => 2,
            self::Tier3 => 3,
        };
    }

    public function isLowerThan(self $other): bool
    {
        return $this->rank() < $other->rank();
    }
}
