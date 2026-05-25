<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ReversalRequest;
use App\Services\Support\ReversalRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReversalRequestController extends ApiController
{
    public function __construct(
        private ReversalRequestService $service,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'ticket_id', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (ReversalRequest $reversal) => $this->format($reversal)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'integer', 'exists:transactions,id'],
            'transaction_reference' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
        ]);

        $reversal = $this->service->create($validated, auth('api')->user());

        return $this->success($this->format($reversal), 'Reversal request submitted.', 201);
    }

    public function approve(Request $request, ReversalRequest $reversalRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->approve(
            $reversalRequest,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated), 'Reversal approved.');
    }

    public function reject(Request $request, ReversalRequest $reversalRequest): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->reject(
            $reversalRequest,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated), 'Reversal rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(ReversalRequest $reversal): array
    {
        return [
            'id' => $reversal->reference,
            'reference' => $reversal->reference,
            'ticket_id' => $reversal->ticket?->reference,
            'txn_ref' => $reversal->transaction_reference,
            'transaction_reference' => $reversal->transaction_reference,
            'transaction_id' => $reversal->transaction_id,
            'customer' => $reversal->ticket?->customer_name,
            'amount' => (float) $reversal->amount,
            'amount_ngn' => (float) $reversal->amount,
            'reason' => $reversal->reason,
            'requested_by' => $reversal->maker?->full_name,
            'maker_id' => $reversal->maker_id,
            'status' => $reversal->status->value,
            'checker' => $reversal->checker?->full_name,
            'checker_notes' => $reversal->checker_notes,
            'reviewed_at' => $reversal->reviewed_at?->toIso8601String(),
            'created_at' => $reversal->created_at?->toIso8601String(),
        ];
    }
}
