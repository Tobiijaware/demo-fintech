<?php

namespace App\Services\Support;

use App\Enums\ReversalStatus;
use App\Models\ReversalRequest;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\MakerCheckerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ReversalRequestService
{
    public function __construct(
        private MakerCheckerService $makerChecker,
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = ReversalRequest::query()
            ->with(['ticket', 'transaction', 'maker', 'checker'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['ticket_id'])) {
            $query->whereHas('ticket', fn ($q) => $q->where('reference', $filters['ticket_id']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('transaction_reference', 'like', "%{$search}%")
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
        $base = ReversalRequest::query();
        $pending = (clone $base)->where('status', ReversalStatus::PendingApproval);

        return [
            'pending_approval' => $pending->count(),
            'pending_amount' => (float) (clone $pending)->sum('amount'),
            'approved_today' => (clone $base)
                ->where('status', ReversalStatus::Approved)
                ->whereDate('reviewed_at', today())
                ->count(),
            'rejected_7d' => (clone $base)
                ->where('status', ReversalStatus::Rejected)
                ->where('reviewed_at', '>=', now()->subDays(7))
                ->count(),
            'processed' => (clone $base)->where('status', ReversalStatus::Processed)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $maker): ReversalRequest
    {
        $this->makerChecker->assertMaker($maker, 'reversals');

        $transaction = null;
        if (! empty($data['transaction_id'])) {
            $transaction = Transaction::query()->findOrFail($data['transaction_id']);
        } elseif (! empty($data['transaction_reference'])) {
            $transaction = Transaction::query()
                ->where('reference', $data['transaction_reference'])
                ->first();
        }

        $ticket = null;
        if (! empty($data['ticket_id'])) {
            $ticket = SupportTicket::query()->where('reference', $data['ticket_id'])->first()
                ?? SupportTicket::query()->find($data['ticket_id']);
        }

        return ReversalRequest::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'ticket_id' => $ticket?->id ?? ($data['ticket_pk'] ?? null),
            'transaction_id' => $transaction?->id,
            'transaction_reference' => $data['transaction_reference'] ?? $transaction?->reference,
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'status' => ReversalStatus::PendingApproval,
            'maker_id' => $maker->id,
        ])->fresh(['ticket', 'transaction', 'maker']);
    }

    public function approve(ReversalRequest $reversal, User $checker, ?string $notes = null): ReversalRequest
    {
        if ($reversal->status !== ReversalStatus::PendingApproval) {
            throw new InvalidArgumentException('Only pending reversal requests can be approved.');
        }

        $maker = $reversal->maker;
        if (! $maker) {
            throw new InvalidArgumentException('Reversal request has no maker.');
        }

        $policy = $this->makerChecker->findPolicyForResource('reversals');
        if (! $policy) {
            throw new InvalidArgumentException('No enforced maker-checker policy for reversals.');
        }

        $this->makerChecker->assertChecker($checker, $policy, $maker);

        $reversal->update([
            'status' => ReversalStatus::Approved,
            'checker_id' => $checker->id,
            'reviewed_at' => now(),
            'checker_notes' => $notes,
        ]);

        $updated = $reversal->fresh(['ticket', 'transaction', 'maker', 'checker']);

        $this->auditLog->record(
            $checker,
            'support.reversal.approved',
            'ReversalRequest',
            $updated->reference,
            "Approved reversal {$updated->reference}",
            [
                'reference' => $updated->reference,
                'amount' => (float) $updated->amount,
                'transaction_reference' => $updated->transaction_reference,
                'maker_id' => $updated->maker_id,
                'checker_notes' => $notes,
            ],
        );

        return $updated;
    }

    public function reject(ReversalRequest $reversal, User $checker, ?string $notes = null): ReversalRequest
    {
        if ($reversal->status !== ReversalStatus::PendingApproval) {
            throw new InvalidArgumentException('Only pending reversal requests can be rejected.');
        }

        $maker = $reversal->maker;
        if (! $maker) {
            throw new InvalidArgumentException('Reversal request has no maker.');
        }

        $policy = $this->makerChecker->findPolicyForResource('reversals');
        if (! $policy) {
            throw new InvalidArgumentException('No enforced maker-checker policy for reversals.');
        }

        $this->makerChecker->assertChecker($checker, $policy, $maker);

        $reversal->update([
            'status' => ReversalStatus::Rejected,
            'checker_id' => $checker->id,
            'reviewed_at' => now(),
            'checker_notes' => $notes,
        ]);

        $updated = $reversal->fresh(['ticket', 'transaction', 'maker', 'checker']);

        $this->auditLog->record(
            $checker,
            'support.reversal.rejected',
            'ReversalRequest',
            $updated->reference,
            "Rejected reversal {$updated->reference}",
            [
                'reference' => $updated->reference,
                'amount' => (float) $updated->amount,
                'transaction_reference' => $updated->transaction_reference,
                'maker_id' => $updated->maker_id,
                'checker_notes' => $notes,
            ],
        );

        return $updated;
    }

    public function generateReference(): string
    {
        $year = now()->year;
        $latest = ReversalRequest::query()
            ->where('reference', 'like', "REV-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 400;
        if ($latest && preg_match('/^REV-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('REV-%d-%03d', $year, $sequence);
            $sequence++;
        } while (ReversalRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
