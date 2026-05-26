<?php

namespace App\Http\Controllers\Api;

use App\Services\Wallet\WalletService;
use App\Services\Wallet\TierLimitService;
use Illuminate\Http\JsonResponse;

class WalletController extends ApiController
{
    public function __construct(
        private WalletService $walletService,
        private TierLimitService $tierLimitService,
    ) {}

    public function balance(): JsonResponse
    {
        $wallet = $this->walletService->getNgnBalance(auth('api')->user());

        return $this->success([
            'currency' => $wallet->currency,
            'account_number' => $wallet->account_number,
            'balance' => (float) $wallet->balance,
            'status' => $wallet->status->value,
        ]);
    }

    public function limits(): JsonResponse
    {
        return $this->success(
            $this->tierLimitService->snapshot(auth('api')->user()),
        );
    }
}
