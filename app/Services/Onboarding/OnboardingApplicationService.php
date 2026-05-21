<?php

namespace App\Services\Onboarding;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\OnboardingChannel;
use App\Enums\OnboardingStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationCheckStatus;
use App\Models\OnboardingApplication;
use App\Models\OnboardingApplicationEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OnboardingApplicationService
{
    public function generateReference(): string
    {
        return 'KYC-'.now()->format('Y').'-'.strtoupper(Str::random(5));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = OnboardingApplication::query()
            ->with(['user', 'maker', 'checker'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at');

        if (! empty($filters['queue'])) {
            $queue = $filters['queue'];
            if ($queue === 'approved') {
                $query->where('status', OnboardingStatus::Approved);
            } elseif ($queue === 'rejected') {
                $query->where('status', OnboardingStatus::Rejected);
            } elseif ($queue === 're_verifications') {
                $query->where('status', OnboardingStatus::ReVerification);
            } else {
                $query->whereIn('status', OnboardingStatus::queueStatuses());
            }
        }

        if (! empty($filters['tier'])) {
            $query->where('tier', $filters['tier']);
        }

        if (! empty($filters['applicant_type'])) {
            $query->where('applicant_type', $filters['applicant_type']);
        }

        if (! empty($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(): array
    {
        $queue = OnboardingApplication::query()
            ->whereIn('status', OnboardingStatus::queueStatuses());

        $overSla = (clone $queue)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();

        $approvedToday = OnboardingApplication::query()
            ->where('status', OnboardingStatus::Approved)
            ->whereDate('reviewed_at', today())
            ->count();

        $queried = OnboardingApplication::query()
            ->where('status', OnboardingStatus::Queried)
            ->count();

        $verified = OnboardingApplication::query()
            ->where('verification_status', VerificationCheckStatus::Verified)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $totalWeek = OnboardingApplication::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'pending_review' => (clone $queue)->count(),
            'over_sla' => $overSla,
            'approved_today' => $approvedToday,
            'queried' => $queried,
            'bvn_match_rate' => $totalWeek > 0 ? round(($verified / $totalWeek) * 100, 1) : 0,
        ];
    }

    public function find(int $id): OnboardingApplication
    {
        return OnboardingApplication::query()
            ->with(['user', 'maker', 'checker', 'events.actor', 'documents'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createInternal(array $data, User $maker): OnboardingApplication
    {
        $app = OnboardingApplication::query()->create([
            'reference' => $this->generateReference(),
            'applicant_type' => $data['applicant_type'],
            'tier' => $data['tier'],
            'status' => OnboardingStatus::Draft,
            'channel' => OnboardingChannel::Internal,
            'verification_status' => $data['verification_status'] ?? VerificationCheckStatus::Pending,
            'business_name' => $data['business_name'] ?? null,
            'proprietor_name' => $data['proprietor_name'] ?? null,
            'location' => $data['location'] ?? null,
            'cac_number' => $data['cac_number'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'bvn_masked' => isset($data['bvn']) ? $this->maskId($data['bvn']) : null,
            'nin_masked' => isset($data['nin']) ? $this->maskId($data['nin']) : null,
            'phone' => $data['phone'] ?? null,
            'estimated_settlement' => $data['estimated_settlement'] ?? null,
            'payload' => $data['payload'] ?? null,
            'linked_agents' => $data['linked_agents'] ?? null,
            'maker_id' => $maker->id,
            'sla_due_at' => now()->addDays(3),
        ]);

        $this->recordEvent($app, $maker, 'created', null, $app->status->value, 'Internal onboarding draft created');

        return $app->fresh(['maker']);
    }

    public function createFromMobileCustomer(User $user, array $overrides = []): OnboardingApplication
    {
        $name = $user->full_name ?: 'Customer';

        $status = $overrides['status'] ?? OnboardingStatus::PendingReview;

        return OnboardingApplication::query()->create([
            'reference' => $this->generateReference(),
            'applicant_type' => ApplicantType::Customer,
            'tier' => $overrides['tier'] ?? AccountTier::Tier1,
            'status' => $status,
            'channel' => OnboardingChannel::Mobile,
            'verification_status' => VerificationCheckStatus::Pending,
            'user_id' => $user->id,
            'business_name' => $overrides['business_name'] ?? $name,
            'proprietor_name' => $name,
            'phone' => $user->phone,
            'bvn_masked' => $user->bvn ? $this->maskId($user->bvn) : null,
            'nin_masked' => $user->nin ? $this->maskId($user->nin) : null,
            'submitted_at' => $overrides['submitted_at'] ?? ($status === OnboardingStatus::PendingReview ? now() : null),
            'sla_due_at' => now()->addDays(2),
        ]);
    }

    public function submit(OnboardingApplication $app, User $actor): OnboardingApplication
    {
        if ($app->status !== OnboardingStatus::Draft) {
            throw new InvalidArgumentException('Only draft applications can be submitted.');
        }

        return $this->transition($app, $actor, OnboardingStatus::PendingReview, 'submitted', 'Submitted for checker review');
    }

    public function approve(OnboardingApplication $app, User $checker): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        if (! in_array($app->status, [OnboardingStatus::PendingReview, OnboardingStatus::Queried, OnboardingStatus::OnHold, OnboardingStatus::ReVerification, OnboardingStatus::Submitted], true)) {
            throw new InvalidArgumentException('Application is not in a reviewable state.');
        }

        $updated = $this->transition($app, $checker, OnboardingStatus::Approved, 'approved', 'Application approved');

        if ($updated->user_id) {
            User::query()->whereKey($updated->user_id)->update([
                'status' => UserStatus::Approved,
                'account_tier' => $updated->tier->value,
            ]);
        }

        return $updated;
    }

    public function reject(OnboardingApplication $app, User $checker, string $reason): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        $updated = $this->transition($app, $checker, OnboardingStatus::Rejected, 'rejected', $reason);
        $updated->update(['rejection_reason' => $reason]);

        if ($updated->user_id) {
            User::query()->whereKey($updated->user_id)->update(['status' => UserStatus::Rejected]);
        }

        return $updated->fresh(['checker', 'maker', 'user']);
    }

    public function queryApplicant(OnboardingApplication $app, User $checker, string $notes): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        $updated = $this->transition($app, $checker, OnboardingStatus::Queried, 'queried', $notes);
        $updated->update(['query_notes' => $notes]);

        return $updated->fresh(['checker', 'maker', 'user']);
    }

    public function hold(OnboardingApplication $app, User $checker, ?string $notes = null): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        return $this->transition($app, $checker, OnboardingStatus::OnHold, 'on_hold', $notes ?? 'Placed on hold');
    }

    protected function assertChecker(OnboardingApplication $app, User $checker): void
    {
        if (! $checker->isBackofficeStaff()) {
            throw new InvalidArgumentException('Only back-office staff can perform checker actions.');
        }

        if ($app->maker_id && (int) $app->maker_id === (int) $checker->id) {
            throw new InvalidArgumentException('Maker-checker rule: you cannot approve an application you initiated.');
        }
    }

    protected function transition(
        OnboardingApplication $app,
        User $actor,
        OnboardingStatus $to,
        string $action,
        ?string $notes = null,
    ): OnboardingApplication {
        $from = $app->status->value;

        $app->update([
            'status' => $to,
            'checker_id' => $actor->id,
            'reviewed_at' => in_array($to, [OnboardingStatus::Approved, OnboardingStatus::Rejected], true) ? now() : $app->reviewed_at,
            'submitted_at' => $app->submitted_at ?? ($to === OnboardingStatus::PendingReview ? now() : $app->submitted_at),
        ]);

        $this->recordEvent($app, $actor, $action, $from, $to->value, $notes);

        return $app->fresh(['checker', 'maker', 'user']);
    }

    protected function recordEvent(
        OnboardingApplication $app,
        ?User $actor,
        string $action,
        ?string $from,
        ?string $to,
        ?string $notes,
    ): void {
        OnboardingApplicationEvent::query()->create([
            'onboarding_application_id' => $app->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
        ]);
    }

    protected function maskId(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?: $value;

        return '****'.substr($digits, -4);
    }
}
