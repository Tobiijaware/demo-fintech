<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFreeze;
use App\Services\Wallet\WalletRestrictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletRestrictionController extends ApiController
{
    public function __construct(
        private WalletRestrictionService $service,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:wallet_id,wallet_account'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id', 'required_without_all:user_id,wallet_account'],
            'wallet_account' => ['nullable', 'string', 'max:32', 'required_without_all:user_id,wallet_id'],
            'reason' => ['required', 'string', 'max:2000'],
            'customer_message' => ['required', 'string', 'max:2000'],
        ]);

        $wallet = $this->resolveWallet($validated);
        $restriction = $this->service->placePnd(
            wallet: $wallet,
            reason: $validated['reason'],
            customerMessage: $validated['customer_message'],
            source: 'compliance',
            actor: auth('api')->user(),
        );

        return $this->success($this->format($restriction), 'Account placed on PND.', 201);
    }

    public function destroy(WalletFreeze $walletFreeze): JsonResponse
    {
        $updated = $this->service->liftRestriction($walletFreeze, auth('api')->user());

        return $this->success($this->format($updated), 'Wallet restriction lifted.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveWallet(array $data): Wallet
    {
        if (! empty($data['wallet_id'])) {
            return Wallet::query()->findOrFail($data['wallet_id']);
        }

        if (! empty($data['wallet_account'])) {
            $wallet = Wallet::query()->where('account_number', $data['wallet_account'])->first();
            if ($wallet) {
                return $wallet;
            }
        }

        if (! empty($data['user_id'])) {
            $wallet = Wallet::query()->where('user_id', $data['user_id'])->first();
            if ($wallet) {
                return $wallet;
            }
        }

        abort(422, 'Wallet not found.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(WalletFreeze $restriction): array
    {
        return [
            'id' => $restriction->id,
            'wallet_id' => $restriction->wallet_id,
            'account_number' => $restriction->wallet?->account_number,
            'user_id' => $restriction->user_id,
            'type' => $restriction->restriction_type,
            'source' => $restriction->source,
            'reason' => $restriction->reason,
            'customer_message' => $restriction->customer_message,
            'active' => $restriction->active,
            'lifted_at' => $restriction->unfrozen_at?->toIso8601String(),
            'created_at' => $restriction->created_at?->toIso8601String(),
        ];
    }
}
