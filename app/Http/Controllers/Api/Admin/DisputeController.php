<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\Dispute;
use App\Services\Support\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputeController extends ApiController
{
    public function __construct(
        private DisputeService $service,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'assignee_id', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (Dispute $dispute) => $this->format($dispute)),
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
            'transaction_reference' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'due_at' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string'],
        ]);

        $dispute = $this->service->create($validated, auth('api')->user());

        return $this->success($this->format($dispute), 'Dispute created.', 201);
    }

    public function update(Request $request, Dispute $dispute): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::enum(DisputeStatus::class)],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'reason' => ['sometimes', 'string'],
            'customer_name' => ['sometimes', 'string', 'max:255'],
        ]);

        if (isset($validated['status']) && count($validated) === 1) {
            $updated = $this->service->updateStatus(
                $dispute,
                DisputeStatus::from($validated['status']),
            );
        } else {
            $updated = $this->service->update($dispute, $validated);
        }

        return $this->success($this->format($updated), 'Dispute updated.');
    }

    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                DisputeStatus::Won->value,
                DisputeStatus::Lost->value,
                DisputeStatus::Closed->value,
            ])],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->resolve(
            $dispute,
            DisputeStatus::from($validated['status']),
            $validated['resolution_notes'] ?? null,
        );

        return $this->success($this->format($updated), 'Dispute resolved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(Dispute $dispute): array
    {
        $metadata = $dispute->ticket?->metadata ?? [];

        return [
            'id' => $dispute->reference,
            'reference' => $dispute->reference,
            'ticket_id' => $dispute->ticket?->reference,
            'txn_ref' => $dispute->transaction_reference,
            'transaction_reference' => $dispute->transaction_reference,
            'customer' => $dispute->customer_name,
            'merchant' => $metadata['merchant'] ?? null,
            'amount' => (float) $dispute->amount,
            'amount_ngn' => (float) $dispute->amount,
            'reason' => $dispute->reason,
            'status' => $dispute->status->value,
            'deadline' => $dispute->due_at?->toIso8601String(),
            'due_at' => $dispute->due_at?->toIso8601String(),
            'assignee' => $dispute->assignee?->full_name ?? 'Unassigned',
            'assignee_id' => $dispute->assignee_id,
            'evidence_due' => (bool) ($metadata['evidence_due'] ?? false),
            'resolution_notes' => $dispute->resolution_notes,
            'opened_at' => $dispute->opened_at?->toIso8601String(),
            'created_at' => $dispute->created_at?->toIso8601String(),
        ];
    }
}
