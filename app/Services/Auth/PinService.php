<?php

namespace App\Services\Auth;

use App\Exceptions\PinException;
use App\Models\User;

class PinService
{
    public function setup(User $user, string $pin): User
    {
        if ($user->hasPinSetup()) {
            throw new PinException('Transaction PIN is already set.', 409);
        }

        $user->update(['pin' => $pin]);

        return $user->refresh();
    }
}
