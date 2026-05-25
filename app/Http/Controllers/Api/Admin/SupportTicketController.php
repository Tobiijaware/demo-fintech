<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class SupportTicketController extends ApiController
{
    public function __construct(
        private SupportTicketService $service,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $assignee = $request->boolean('mine')
            ? auth('api')->user()
            : null;

        return $this->success($this->service->stats($assignee));
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'assignee_id', 'assignee', 'status', 'sla_breached', 'category', 'search', 'per_page',
        ]);

        if ($request->boolean('mine')) {
            $filters['assignee_id'] = auth('api')->id();
        }

        $paginator = $this->service->list($filters);

        return $this->success([
            'items' => collect($paginator->items())->map(fn (SupportTicket $ticket) => $this->format($ticket)),
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
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string'],
            'status' => ['nullable', 'string'],
            'priority' => ['required', 'string'],
            'channel' => ['required', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'wallet_id' => ['nullable', 'integer', 'exists:wallets,id'],
            'sla_due_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $ticket = $this->service->create($validated, auth('api')->user());

        return $this->success($this->format($ticket, true), 'Support ticket created.', 201);
    }

    public function show(SupportTicket $supportTicket): JsonResponse
    {
        $ticket = $this->service->find($supportTicket->reference);

        return $this->success($this->format($ticket, true));
    }

    public function update(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string'],
            'channel' => ['sometimes', 'string'],
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $ticket = $this->service->update($supportTicket, $validated, auth('api')->user());

        return $this->success($this->format($ticket, true), 'Support ticket updated.');
    }

    public function assign(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = $this->service->assign(
            $supportTicket,
            $validated['assignee_id'] ?? null,
            auth('api')->user(),
        );

        return $this->success($this->format($ticket, true), 'Ticket assigned.');
    }

    public function resolve(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $ticket = $this->service->resolve(
            $supportTicket,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($ticket, true), 'Ticket resolved.');
    }

    public function notes(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string'],
        ]);

        $event = $this->service->addNote($supportTicket, auth('api')->user(), $validated['notes']);

        return $this->success([
            'id' => $event->id,
            'action' => $event->action,
            'notes' => $event->notes,
            'actor' => $event->actor?->full_name,
            'created_at' => $event->created_at?->toIso8601String(),
        ], 'Note added.', 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(SupportTicket $ticket, bool $detailed = false): array
    {
        $metadata = $ticket->metadata ?? [];

        $data = [
            'id' => $ticket->reference,
            'reference' => $ticket->reference,
            'issue' => $ticket->subject,
            'sub' => $ticket->description,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'category' => $ticket->category->value,
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'channel' => $ticket->channel->value,
            'assignee' => $ticket->assignee?->full_name,
            'assignee_id' => $ticket->assignee_id,
            'customer' => $ticket->customer_name,
            'customer_name' => $ticket->customer_name,
            'customer_phone' => $ticket->customer_phone,
            'customer_email' => $ticket->customer_email,
            'customer_user_id' => $ticket->customer_user_id,
            'wallet_id' => $metadata['wallet_display'] ?? ($ticket->wallet ? 'W-'.$ticket->wallet->id : null),
            'wallet_account' => $ticket->wallet?->account_number,
            'wallet_balance' => $ticket->wallet ? (float) $ticket->wallet->balance : null,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            'sla_breached' => $ticket->sla_breached,
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'txn_ref' => $metadata['txn_ref'] ?? null,
            'metadata' => $metadata,
        ];

        if ($detailed) {
            $data['events'] = $ticket->relationLoaded('events')
                ? $ticket->events->map(fn ($event) => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'notes' => $event->notes,
                    'actor' => $event->actor?->full_name,
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->values()->all()
                : [];
            $data['suggested_action'] = $metadata['suggested_action'] ?? null;
            $data['disputed_txn_detail'] = $metadata['disputed_txn_detail'] ?? null;
            $data['workflow_steps'] = $metadata['workflow_steps'] ?? [];
        }

        return $data;
    }
}
