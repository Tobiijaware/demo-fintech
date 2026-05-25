<?php

namespace App\Services\Aml;

use App\Enums\StrFilingStatus;
use App\Models\AmlCase;
use App\Models\StrFiling;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\MakerCheckerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class StrFilingService
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
        $query = StrFiling::query()
            ->with(['amlCase', 'maker', 'checker'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(string $reference): StrFiling
    {
        return StrFiling::query()
            ->with(['amlCase', 'maker', 'checker'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $maker): StrFiling
    {
        $this->makerChecker->assertMaker($maker, 'str_filings');

        $case = null;
        if (! empty($data['case_id'])) {
            $case = AmlCase::query()->where('reference', $data['case_id'])->first()
                ?? AmlCase::query()->find($data['case_id']);
        }

        return StrFiling::query()->create([
            'reference' => $data['reference'] ?? $this->generateReference(),
            'case_id' => $case?->id,
            'title' => $data['title'],
            'narrative' => $data['narrative'] ?? null,
            'amount_ngn' => $data['amount_ngn'] ?? 0,
            'status' => StrFilingStatus::Draft,
            'maker_id' => $maker->id,
        ])->fresh(['amlCase', 'maker']);
    }

    public function submitForReview(StrFiling $filing, User $maker, ?string $notes = null): StrFiling
    {
        if ($filing->status !== StrFilingStatus::Draft && $filing->status !== StrFilingStatus::Rejected) {
            throw new InvalidArgumentException('Only draft or rejected STR filings can be submitted for review.');
        }

        if ((int) $filing->maker_id !== (int) $maker->id) {
            throw new InvalidArgumentException('Only the filing maker can submit for review.');
        }

        $this->makerChecker->assertMaker($maker, 'str_filings');

        $filing->update([
            'status' => StrFilingStatus::PendingReview,
        ]);

        $updated = $filing->fresh(['amlCase', 'maker', 'checker']);

        $this->auditLog->record(
            $maker,
            'aml.str.submitted',
            'StrFiling',
            $updated->reference,
            "Submitted STR {$updated->reference} for compliance review",
            [
                'reference' => $updated->reference,
                'case_id' => $updated->amlCase?->reference,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    public function approve(StrFiling $filing, User $checker, ?string $notes = null): StrFiling
    {
        if ($filing->status !== StrFilingStatus::PendingReview) {
            throw new InvalidArgumentException('Only STR filings pending review can be approved.');
        }

        $maker = $filing->maker;
        if (! $maker) {
            throw new InvalidArgumentException('STR filing has no maker.');
        }

        $policy = $this->makerChecker->findPolicyForResource('str_filings');
        if (! $policy) {
            throw new InvalidArgumentException('No enforced maker-checker policy for STR filings.');
        }

        $this->makerChecker->assertChecker($checker, $policy, $maker);

        $filing->update([
            'status' => StrFilingStatus::Submitted,
            'checker_id' => $checker->id,
            'submitted_at' => now(),
        ]);

        $updated = $filing->fresh(['amlCase', 'maker', 'checker']);

        $this->auditLog->record(
            $checker,
            'aml.str.approved',
            'StrFiling',
            $updated->reference,
            "Approved and submitted STR {$updated->reference} to NFIU queue",
            [
                'reference' => $updated->reference,
                'case_id' => $updated->amlCase?->reference,
                'maker_id' => $updated->maker_id,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    public function reject(StrFiling $filing, User $checker, ?string $notes = null): StrFiling
    {
        if ($filing->status !== StrFilingStatus::PendingReview) {
            throw new InvalidArgumentException('Only STR filings pending review can be rejected.');
        }

        $maker = $filing->maker;
        if (! $maker) {
            throw new InvalidArgumentException('STR filing has no maker.');
        }

        $policy = $this->makerChecker->findPolicyForResource('str_filings');
        if (! $policy) {
            throw new InvalidArgumentException('No enforced maker-checker policy for STR filings.');
        }

        $this->makerChecker->assertChecker($checker, $policy, $maker);

        $filing->update([
            'status' => StrFilingStatus::Rejected,
            'checker_id' => $checker->id,
        ]);

        $updated = $filing->fresh(['amlCase', 'maker', 'checker']);

        $this->auditLog->record(
            $checker,
            'aml.str.rejected',
            'StrFiling',
            $updated->reference,
            "Rejected STR {$updated->reference}",
            [
                'reference' => $updated->reference,
                'maker_id' => $updated->maker_id,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    public function generateReference(): string
    {
        $year = now()->year;
        $latest = StrFiling::query()
            ->where('reference', 'like', "STR-{$year}-%")
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if ($latest && preg_match('/^STR-\d{4}-(\d+)/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('STR-%d-%04d', $year, $sequence);
            $sequence++;
        } while (StrFiling::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
