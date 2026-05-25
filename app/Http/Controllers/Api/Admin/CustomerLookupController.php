<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Support\CustomerLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLookupController extends ApiController
{
    public function __construct(
        private CustomerLookupService $service,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $customers = $this->service->search($validated['q'] ?? null);

        return $this->success([
            'items' => $customers->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'wallet_id' => $user->wallet?->id,
                'wallet_account' => $user->wallet?->account_number,
                'wallet_balance' => $user->wallet ? (float) $user->wallet->balance : null,
                'account_tier' => $user->account_tier,
                'status' => $user->status->value,
            ])->values()->all(),
        ]);
    }
}
