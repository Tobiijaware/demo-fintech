<?php

namespace App\Services\Support;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DisputeService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Dispute::query()
            ->with(['ticket', 'assignee'])
            ->orderByDesc('opened_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', (int) $filters['assignee_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $base = Dispute::query();
        $open = (clone $base)->whereIn('status', [DisputeStatus::Open, DisputeStatus::UnderReview]);

        return [
            'open' => (clone $base)->where('status', DisputeStatus::Open)->count(),
            'under_review' => (clone $base)->where('status', DisputeStatus::UnderReview)->count(),
            'past_deadline' => (clone $open)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'won_mtd' => (clone $base)
                ->where('status', DisputeStatus::Won)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
            'amount_at_risk' => (float) (clone $open)->sum('amount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Dispute
    {
        $ticket = null;
        if (! empty($data['ticket_id'])) {
            $ticket = SupportTicket::query()->where('reference', $data['ticket_id'])->first()
                ?? SupportTicket::query()->find($data['ticket_id']);
        }

        return Dispute::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'ticket_id' => $ticket?->id ?? ($data['ticket_pk'] ?? null),
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'status' => $data['status'] ?? DisputeStatus::Open,
            'customer_name' => $data['customer_name'],
            'opened_at' => isset($data['opened_at']) ? Carbon::parse($data['opened_at']) : now(),
            'due_at' => isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
            'assignee_id' => $data['assignee_id'] ?? $actor->id,
        ])->fresh(['ticket', 'assignee']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Dispute $dispute, array $data): Dispute
    {
        $dispute->update($data);

        return $dispute->fresh(['ticket', 'assignee']);
    }

    public function updateStatus(Dispute $dispute, DisputeStatus $status): Dispute
    {
        $dispute->update(['status' => $status]);

        return $dispute->fresh(['ticket', 'assignee']);
    }

    public function resolve(Dispute $dispute, DisputeStatus $status, ?string $resolutionNotes = null): Dispute
    {
        if (! in_array($status, [DisputeStatus::Won, DisputeStatus::Lost, DisputeStatus::Closed], true)) {
            throw new InvalidArgumentException('Dispute resolution requires Won, Lost, or Closed status.');
        }

        $dispute->update([
            'status' => $status,
            'resolution_notes' => $resolutionNotes,
        ]);

        return $dispute->fresh(['ticket', 'assignee']);
    }

    public function generateReference(): string
    {
        $year = now()->year;
        $latest = Dispute::query()
            ->where('reference', 'like', "DSP-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 80;
        if ($latest && preg_match('/^DSP-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('DSP-%d-%03d', $year, $sequence);
            $sequence++;
        } while (Dispute::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
