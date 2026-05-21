<?php

namespace App\Services\Auth;

use App\Exceptions\PinException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    public function verify(User $user, string $pin): void
    {
        if (! $user->hasPinSetup()) {
            throw new PinException('Set up your transaction PIN first.', 403);
        }

        if (! Hash::check($pin, $user->pin)) {
            throw new PinException('Incorrect transaction PIN.', 422);
        }
    }
}
