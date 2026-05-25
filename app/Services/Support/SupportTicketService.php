<?php

namespace App\Services\Support;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SupportTicketService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['assignee', 'customerUser', 'wallet'])
            ->orderByDesc('created_at');

        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', (int) $filters['assignee_id']);
        }

        if (! empty($filters['assignee'])) {
            $assignee = $filters['assignee'];
            $query->whereHas('assignee', function ($q) use ($assignee) {
                if (is_numeric($assignee)) {
                    $q->where('id', (int) $assignee);
                } else {
                    $q->where('email', 'like', "%{$assignee}%")
                        ->orWhereRaw("concat(firstname, ' ', lastname) like ?", ["%{$assignee}%"]);
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('sla_breached', $filters) && $filters['sla_breached'] !== null && $filters['sla_breached'] !== '') {
            $query->where('sla_breached', filter_var($filters['sla_breached'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(?User $assignee = null): array
    {
        $base = SupportTicket::query();

        if ($assignee) {
            $base->where('assignee_id', $assignee->id);
        }

        $openStatuses = [
            TicketStatus::Open,
            TicketStatus::Pending,
            TicketStatus::InReview,
            TicketStatus::Escalated,
            TicketStatus::AwaitingCustomer,
        ];

        $open = (clone $base)->whereIn('status', $openStatuses);
        $resolvedToday = (clone $base)
            ->where('status', TicketStatus::Resolved)
            ->whereDate('resolved_at', today());

        return [
            'open' => (clone $open)->count(),
            'open_over_sla' => (clone $open)->where('sla_breached', true)->count(),
            'unassigned' => (clone $open)->whereNull('assignee_id')->count(),
            'resolved_today' => $resolvedToday->count(),
            'escalated' => (clone $base)->where('status', TicketStatus::Escalated)->count(),
            'sla_breached' => (clone $base)->where('sla_breached', true)->count(),
            'resolved_24h' => (clone $base)
                ->where('status', TicketStatus::Resolved)
                ->where('resolved_at', '>=', now()->subDay())
                ->count(),
            'by_category' => collect(TicketCategory::cases())
                ->mapWithKeys(fn (TicketCategory $category) => [
                    $category->value => (clone $base)->where('category', $category)->count(),
                ])
                ->all(),
        ];
    }

    public function find(string $reference): SupportTicket
    {
        return SupportTicket::query()
            ->with(['assignee', 'customerUser', 'wallet.user', 'events.actor', 'reversalRequests', 'disputes'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): SupportTicket
    {
        $ticket = SupportTicket::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'status' => $data['status'] ?? TicketStatus::Open,
            'priority' => $data['priority'],
            'channel' => $data['channel'],
            'assignee_id' => $data['assignee_id'] ?? $actor->id,
            'customer_user_id' => $data['customer_user_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'wallet_id' => $data['wallet_id'] ?? null,
            'sla_due_at' => isset($data['sla_due_at']) ? Carbon::parse($data['sla_due_at']) : now()->addHours(4),
            'sla_breached' => (bool) ($data['sla_breached'] ?? false),
            'metadata' => $data['metadata'] ?? null,
            'maker_id' => $actor->id,
        ]);

        $this->recordEvent($ticket, $actor, 'created', 'Ticket created');

        $this->auditLog->record(
            $actor,
            'support.ticket.created',
            'SupportTicket',
            $ticket->reference,
            "Created support ticket {$ticket->reference}",
            [
                'reference' => $ticket->reference,
                'subject' => $ticket->subject,
                'category' => $ticket->category->value,
                'status' => $ticket->status->value,
            ],
        );

        return $ticket->fresh(['assignee', 'customerUser', 'wallet']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SupportTicket $ticket, array $data, User $actor): SupportTicket
    {
        $ticket->update($data);

        if ($ticket->sla_due_at && $ticket->sla_due_at->isPast() && ! $ticket->resolved_at) {
            $ticket->update(['sla_breached' => true]);
        }

        $updated = $ticket->fresh(['assignee', 'customerUser', 'wallet']);

        $this->recordEvent($updated, $actor, 'updated', 'Ticket updated');

        return $updated;
    }

    public function assign(SupportTicket $ticket, ?int $assigneeId, User $actor): SupportTicket
    {
        $ticket->update(['assignee_id' => $assigneeId]);

        $updated = $ticket->fresh(['assignee', 'customerUser', 'wallet']);

        $assigneeName = $updated->assignee?->full_name ?? 'Unassigned';
        $this->recordEvent($updated, $actor, 'assigned', "Assigned to {$assigneeName}");

        return $updated;
    }

    public function resolve(SupportTicket $ticket, User $actor, ?string $notes = null): SupportTicket
    {
        if ($ticket->status === TicketStatus::Resolved) {
            throw new InvalidArgumentException('Ticket is already resolved.');
        }

        $ticket->update([
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $updated = $ticket->fresh(['assignee', 'customerUser', 'wallet']);

        $this->recordEvent($updated, $actor, 'resolved', $notes ?? 'Ticket resolved');

        $this->auditLog->record(
            $actor,
            'support.ticket.resolved',
            'SupportTicket',
            $updated->reference,
            "Resolved support ticket {$updated->reference}",
            [
                'reference' => $updated->reference,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    public function addNote(SupportTicket $ticket, User $actor, string $notes): SupportTicketEvent
    {
        return $this->recordEvent($ticket, $actor, 'note', $notes);
    }

    public function generateReference(): string
    {
        $latest = SupportTicket::query()->orderByDesc('id')->value('reference');
        $sequence = 9281;

        if ($latest && preg_match('/^T-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = 'T-'.$sequence;
            $sequence++;
        } while (SupportTicket::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function recordEvent(SupportTicket $ticket, User $actor, string $action, ?string $notes = null): SupportTicketEvent
    {
        return SupportTicketEvent::query()->create([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
