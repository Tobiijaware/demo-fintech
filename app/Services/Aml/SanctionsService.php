<?php

namespace App\Services\Aml;

use App\Enums\SanctionHitStatus;
use App\Models\SanctionsHit;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class SanctionsService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = SanctionsHit::query()
            ->with('reviewedBy')
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('matched_name', 'like', "%{$search}%")
                    ->orWhere('subject_id', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function find(string $reference): SanctionsHit
    {
        return SanctionsHit::query()
            ->with('reviewedBy')
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function markFalsePositive(SanctionsHit $hit, User $reviewer, ?string $notes = null): SanctionsHit
    {
        if ($hit->status !== SanctionHitStatus::PendingReview) {
            throw new InvalidArgumentException('Only pending hits can be marked as false positive.');
        }

        $hit->update([
            'status' => SanctionHitStatus::FalsePositive,
            'reviewed_by_id' => $reviewer->id,
        ]);

        return $hit->fresh(['reviewedBy']);
    }

    public function confirmMatch(SanctionsHit $hit, User $reviewer, ?string $notes = null): SanctionsHit
    {
        if ($hit->status !== SanctionHitStatus::PendingReview) {
            throw new InvalidArgumentException('Only pending hits can be confirmed.');
        }

        $hit->update([
            'status' => SanctionHitStatus::ConfirmedMatch,
            'reviewed_by_id' => $reviewer->id,
        ]);

        return $hit->fresh(['reviewedBy']);
    }

    public function generateReference(): string
    {
        $latest = SanctionsHit::query()
            ->where('reference', 'like', 'SAN-%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 43900;
        if ($latest && preg_match('/^SAN-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('SAN-%05d', $sequence);
            $sequence++;
        } while (SanctionsHit::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
