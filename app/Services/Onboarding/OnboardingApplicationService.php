<?php

namespace App\Services\Onboarding;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\OnboardingChannel;
use App\Enums\OnboardingStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\VerificationCheckStatus;
use App\Models\MakerCheckerPolicy;
use App\Models\OnboardingApplication;
use App\Models\OnboardingApplicationEvent;
use App\Models\User;
use App\Services\Agents\AgentProvisioningService;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\MakerCheckerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OnboardingApplicationService
{
    public function __construct(
        private MakerCheckerService $makerChecker,
        private AuditLogService $auditLog,
        private AgentProvisioningService $agentProvisioning,
        private OnboardingIdentityService $onboardingIdentity,
    ) {}

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
                $query->where(function ($sub) {
                    $sub->where('applicant_type', '!=', ApplicantType::Customer)
                        ->orWhere('tier', '!=', AccountTier::Tier1)
                        ->orWhereNotNull('bvn_masked')
                        ->orWhereNotNull('nin_masked')
                        ->orHas('documents');
                });
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
        [$verificationStatus, $identityPayload] = $this->resolveIdentityVerification($data);

        $payload = array_merge($data['payload'] ?? [], $identityPayload);

        $app = OnboardingApplication::query()->create([
            'reference' => $this->generateReference(),
            'applicant_type' => $data['applicant_type'],
            'tier' => $data['tier'],
            'status' => OnboardingStatus::Draft,
            'channel' => OnboardingChannel::Internal,
            'verification_status' => $data['verification_status'] ?? $verificationStatus,
            'business_name' => $data['business_name'] ?? null,
            'proprietor_name' => $data['proprietor_name'] ?? null,
            'location' => $data['location'] ?? null,
            'cac_number' => $data['cac_number'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'bvn_masked' => isset($data['bvn']) ? $this->maskId($data['bvn']) : null,
            'nin_masked' => isset($data['nin']) ? $this->maskId($data['nin']) : null,
            'phone' => $data['phone'] ?? null,
            'estimated_settlement' => $data['estimated_settlement'] ?? null,
            'payload' => $payload ?: null,
            'linked_agents' => $data['linked_agents'] ?? null,
            'maker_id' => $maker->id,
            'sla_due_at' => now()->addDays(3),
        ]);

        $this->recordEvent($app, $maker, 'created', null, $app->status->value, 'Internal onboarding draft created');

        $this->auditLog->record(
            $maker,
            'onboarding.created',
            'OnboardingApplication',
            (string) $app->id,
            "Created internal onboarding draft {$app->reference}",
            [
                'reference' => $app->reference,
                'applicant_type' => $app->applicant_type->value,
                'tier' => $app->tier->value,
                'channel' => $app->channel->value,
            ],
        );

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

        $updated = $this->transition($app, $actor, OnboardingStatus::PendingReview, 'submitted', 'Submitted for checker review');

        $this->auditLog->record(
            $actor,
            'onboarding.submitted',
            'OnboardingApplication',
            (string) $updated->id,
            "Submitted onboarding application {$updated->reference} for review",
            [
                'reference' => $updated->reference,
                'from_status' => OnboardingStatus::Draft->value,
                'to_status' => OnboardingStatus::PendingReview->value,
            ],
        );

        return $updated;
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

        if ($updated->applicant_type === ApplicantType::Agent) {
            $this->agentProvisioning->provisionFromOnboarding($updated, $checker);
        }

        $this->auditLog->record(
            $checker,
            'onboarding.approved',
            'OnboardingApplication',
            (string) $updated->id,
            "Approved onboarding application {$updated->reference}",
            [
                'reference' => $updated->reference,
                'tier' => $updated->tier->value,
                'user_id' => $updated->user_id,
            ],
        );

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

        $this->auditLog->record(
            $checker,
            'onboarding.rejected',
            'OnboardingApplication',
            (string) $updated->id,
            "Rejected onboarding application {$updated->reference}",
            [
                'reference' => $updated->reference,
                'reason' => $reason,
            ],
        );

        return $updated->fresh(['checker', 'maker', 'user']);
    }

    public function queryApplicant(OnboardingApplication $app, User $checker, string $notes): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        $updated = $this->transition($app, $checker, OnboardingStatus::Queried, 'queried', $notes);
        $updated->update(['query_notes' => $notes]);

        $this->auditLog->record(
            $checker,
            'onboarding.queried',
            'OnboardingApplication',
            (string) $updated->id,
            "Queried applicant on onboarding application {$updated->reference}",
            [
                'reference' => $updated->reference,
                'notes' => $notes,
            ],
        );

        return $updated->fresh(['checker', 'maker', 'user']);
    }

    public function hold(OnboardingApplication $app, User $checker, ?string $notes = null): OnboardingApplication
    {
        $this->assertChecker($app, $checker);

        $updated = $this->transition($app, $checker, OnboardingStatus::OnHold, 'on_hold', $notes ?? 'Placed on hold');

        $this->auditLog->record(
            $checker,
            'onboarding.hold',
            'OnboardingApplication',
            (string) $updated->id,
            "Placed onboarding application {$updated->reference} on hold",
            [
                'reference' => $updated->reference,
                'notes' => $notes,
            ],
        );

        return $updated;
    }

    protected function assertChecker(OnboardingApplication $app, User $checker): void
    {
        if (! $checker->isBackofficeStaff()) {
            throw new InvalidArgumentException('Only back-office staff can perform checker actions.');
        }

        $maker = $app->maker;
        if (! $maker) {
            return;
        }

        if ((int) $app->maker_id === (int) $checker->id) {
            throw new InvalidArgumentException('Maker-checker rule: you cannot approve an application you initiated.');
        }

        $policy = $this->resolveOnboardingPolicy($app);
        if ($policy) {
            $this->makerChecker->assertChecker($checker, $policy, $maker);
        }
    }

    protected function resolveOnboardingPolicy(OnboardingApplication $app): ?MakerCheckerPolicy
    {
        if ($app->applicant_type === ApplicantType::Agent) {
            return $this->makerChecker->findPolicyForResource('agent_records');
        }

        if ($app->tier === AccountTier::Tier3) {
            return $this->makerChecker->findPolicyForResource('kyc_applications');
        }

        return null;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: VerificationCheckStatus, 1: array<string, mixed>}
     */
    protected function resolveIdentityVerification(array $data): array
    {
        $payload = [];
        $bvnOk = null;
        $ninOk = null;

        if (! empty($data['bvn'])) {
            [$first, $last] = $this->splitName($data['proprietor_name'] ?? $data['business_name'] ?? 'Applicant');
            $bvnResult = $this->onboardingIdentity->verifyBvn($data['bvn'], $first, $last);
            $payload['bvn_verification'] = $bvnResult;
            $bvnOk = $bvnResult['valid'] ?? false;
        }

        if (! empty($data['nin'])) {
            $ninResult = $this->onboardingIdentity->verifyNin($data['nin']);
            $payload['nin_verification'] = $ninResult;
            $ninOk = $ninResult['valid'] ?? false;
        }

        if ($bvnOk === false) {
            return [VerificationCheckStatus::BvnMismatch, $payload];
        }

        if ($ninOk === false) {
            return [VerificationCheckStatus::NinMismatch, $payload];
        }

        if ($bvnOk === true || $ninOk === true) {
            return [VerificationCheckStatus::Verified, $payload];
        }

        return [VerificationCheckStatus::Pending, $payload];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $parts[0] ?? 'Applicant';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first, $last];
    }

    protected function maskId(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?: $value;

        return '****'.substr($digits, -4);
    }
}
