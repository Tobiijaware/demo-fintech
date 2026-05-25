<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\WalletFreeze;
use App\Services\Aml\WalletFreezeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletFreezeController extends ApiController
{
    public function __construct(
        private WalletFreezeService $service,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:wallet_id,wallet_account'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id', 'required_without_all:user_id,wallet_account'],
            'wallet_account' => ['nullable', 'string', 'max:32', 'required_without_all:user_id,wallet_id'],
            'case_id' => ['nullable', 'string'],
            'reason' => ['required', 'string'],
        ]);

        $freeze = $this->service->freeze($validated, auth('api')->user());

        return $this->success($this->format($freeze), 'Wallet frozen.', 201);
    }

    public function destroy(WalletFreeze $walletFreeze): JsonResponse
    {
        $updated = $this->service->unfreeze($walletFreeze, auth('api')->user());

        return $this->success($this->format($updated), 'Wallet unfrozen.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(WalletFreeze $freeze): array
    {
        return [
            'id' => $freeze->id,
            'wallet_id' => $freeze->wallet_id,
            'account_number' => $freeze->wallet?->account_number,
            'user_id' => $freeze->user_id,
            'case_id' => $freeze->amlCase?->reference,
            'reason' => $freeze->reason,
            'frozen_by' => $freeze->frozenBy?->full_name,
            'active' => $freeze->active,
            'unfrozen_at' => $freeze->unfrozen_at?->toIso8601String(),
            'created_at' => $freeze->created_at?->toIso8601String(),
        ];
    }
}
