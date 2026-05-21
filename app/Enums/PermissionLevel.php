<?php

namespace App\Enums;

enum PermissionLevel: string
{
    case Read = 'read';
    case Write = 'write';
    case Append = 'append';

    public function rank(): int
    {
        return match ($this) {
            self::Read => 1,
            self::Append => 2,
            self::Write => 3,
        };
    }

    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }
}
