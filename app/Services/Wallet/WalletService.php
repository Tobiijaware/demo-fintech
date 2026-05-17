<?php

namespace App\Services\Wallet;

use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;

class WalletService
{
    public function createNgnWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'currency' => 'NGN',
            ],
            [
                'account_number' => \generate_wallet_account_number(),
                'balance' => 0,
                'status' => WalletStatus::Active,
            ]
        );
    }

    public function getNgnBalance(User $user): Wallet
    {
        $wallet = $user->wallet;

        if (! $wallet) {
            $wallet = $this->createNgnWallet($user);
        }

        return $wallet;
    }
}
