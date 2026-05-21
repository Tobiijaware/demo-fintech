<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Transaction;
use App\Services\Wallet\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function __construct(private TransactionService $transactionService) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->transactionService->adminStats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->transactionService->adminList(
            $request->string('search')->toString() ?: null,
            $request->string('status')->toString() ?: null,
        );

        $items = collect($paginator->items())->map(fn (Transaction $tx) => [
            'id' => $tx->reference,
            'reference' => $tx->reference,
            'session_id' => $tx->session_id,
            'type' => $tx->type->value,
            'direction' => $tx->direction->value,
            'amount' => (float) $tx->amount,
            'fee' => (float) $tx->fee,
            'status' => $tx->status->value,
            'customer' => $tx->user
                ? trim("{$tx->user->firstname} {$tx->user->lastname}")
                : null,
            'customer_email' => $tx->user?->email,
            'wallet_account' => $tx->wallet?->account_number,
            'counterparty_name' => $tx->counterparty_name,
            'counterparty_account' => $tx->counterparty_account,
            'narrative' => $tx->narrative,
            'occurred_at' => $tx->created_at?->toIso8601String(),
        ]);

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
