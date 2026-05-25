<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Transaction;
use App\Services\Wallet\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $items = collect($paginator->items())
            ->map(fn (Transaction $tx) => $this->transactionService->formatForAdmin($tx));

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
        $transaction = $this->transactionService->adminFind($reference);

        return $this->success($this->transactionService->formatForAdmin($transaction, true));
    }

    public function export(Request $request): StreamedResponse
    {
        $search = $request->string('search')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $filename = 'transactions-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($search, $status) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'reference',
                'session_id',
                'type',
                'direction',
                'amount',
                'fee',
                'currency',
                'status',
                'customer',
                'customer_email',
                'wallet_account',
                'counterparty_name',
                'counterparty_account',
                'counterparty_bank',
                'narrative',
                'occurred_at',
            ]);

            $this->transactionService->adminQuery($search, $status)
                ->chunk(200, function ($transactions) use ($handle) {
                    foreach ($transactions as $tx) {
                        fputcsv($handle, [
                            $tx->reference,
                            $tx->session_id,
                            $tx->type->value,
                            $tx->direction->value,
                            (float) $tx->amount,
                            (float) $tx->fee,
                            $tx->currency,
                            $tx->status->value,
                            $tx->user ? trim("{$tx->user->firstname} {$tx->user->lastname}") : null,
                            $tx->user?->email,
                            $tx->wallet?->account_number,
                            $tx->counterparty_name,
                            $tx->counterparty_account,
                            $tx->counterparty_bank,
                            $tx->narrative,
                            $tx->created_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function retry(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();
        $updated = $this->transactionService->retryFailed(
            $transaction,
            auth('api')->user(),
            $validated['note'] ?? null,
        );

        return $this->success(
            $this->transactionService->formatForAdmin($updated, true),
            'Transaction retried successfully.',
        );
    }

    public function resolve(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                TransactionStatus::Success->value,
                TransactionStatus::Failed->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();
        $updated = $this->transactionService->resolve(
            $transaction,
            auth('api')->user(),
            TransactionStatus::from($validated['status']),
            $validated['notes'] ?? null,
        );

        return $this->success(
            $this->transactionService->formatForAdmin($updated, true),
            'Transaction resolved.',
        );
    }
}
