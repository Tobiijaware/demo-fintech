<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Wallet\ResolveWalletRequest;
use App\Http\Requests\Wallet\WalletTransferRequest;
use App\Services\Wallet\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function __construct(private TransactionService $transactionService) {}

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $paginator = $this->transactionService->listForUser(
            $user,
            $request->string('search')->toString() ?: null,
        );

        $items = collect($paginator->items())
            ->map(fn ($tx) => $this->transactionService->formatForMobile($tx, $user));

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

    public function show(string $reference): JsonResponse
    {
        $user = auth('api')->user();
        $tx = $this->transactionService->findForUser($user, $reference);

        return $this->success($this->transactionService->formatForMobile($tx, $user));
    }

    public function resolve(ResolveWalletRequest $request): JsonResponse
    {
        $data = $this->transactionService->resolveWalletAccount(
            auth('api')->user(),
            $request->validated('account_number'),
        );

        return $this->success($data);
    }

    public function transfer(WalletTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->transactionService->transfer(
            auth('api')->user(),
            $validated['account_number'],
            (float) $validated['amount'],
            $validated['pin'],
            $validated['remark'] ?? null,
        );

        return $this->success($result, 'Transfer successful.');
    }
}
