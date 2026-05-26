<?php

namespace App\Services\Kyc;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\KycLevel;
use App\Enums\KycStatus;
use App\Enums\OnboardingChannel;
use App\Enums\OnboardingDocumentType;
use App\Enums\OnboardingStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationCheckStatus;
use App\Exceptions\RegistrationException;
use App\Models\OnboardingApplication;
use App\Models\OnboardingDocument;
use App\Models\User;
use App\Services\Onboarding\OnboardingApplicationService;
use App\Services\Onboarding\OnboardingDocumentStorage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class CustomerKycService
{
    public function __construct(
        private OnboardingApplicationService $onboardingService,
        private OnboardingDocumentStorage $documentStorage,
        private TierCriteriaService $tierCriteriaService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function tierRequirements(string $applicantType, string $targetTier): array
    {
        return $this->tierCriteriaService->tierRequirements($applicantType, $targetTier);
    }

    /**
     * @return array<string, mixed>
     */
    public function allTierDefinitions(string $applicantType = 'customer'): array
    {
        return $this->tierCriteriaService->allTierDefinitions($applicantType);
    }

    /**
     * @return array<string, mixed>
     */
    public function progress(User $user, ?string $targetTier = null): array
    {
        $applicantType = ApplicantType::Customer->value;
        $currentTier = $user->account_tier ?? AccountTier::Tier1->value;
        $targetTier = $targetTier ?? config('onboarding.default_customer_target_tier', 'tier_2');

        $application = $this->ensureUpgradeApplication($user, AccountTier::from($targetTier));
        $requirements = $this->tierRequirements($applicantType, $targetTier);
        $completed = $this->tierCriteriaService->completedForUser($user, $application, $requirements);

        $ready = $this->tierCriteriaService->isReadyToSubmit($requirements, $completed);
        $obligations = $this->tierCriteriaService->outstandingObligations($user);

        return [
            'account_tier' => $currentTier,
            'target_tier' => $targetTier,
            'kyc_status' => $this->resolveKycStatus($user, $application),
            'compliance_status' => $this->tierCriteriaService->resolveComplianceStatus($obligations),
            'outstanding_obligations' => $obligations,
            'application' => [
                'id' => $application->id,
                'reference' => $application->reference,
                'status' => $application->status->value,
                'tier' => $application->tier->value,
            ],
            'requirements' => $requirements,
            'completed' => $completed,
            'ready_to_submit' => $ready,
            'can_submit' => $ready && in_array($application->status, [
                OnboardingStatus::Draft,
                OnboardingStatus::Queried,
            ], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveField(User $user, string $key, string $value): array
    {
        $column = match ($key) {
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'phone' => 'phone',
            'date_of_birth', 'dob' => 'dob',
            default => null,
        };

        if ($column === null) {
            throw new RegistrationException('This field cannot be updated here.', 422);
        }

        $normalized = match ($column) {
            'firstname', 'lastname' => trim($value),
            'phone' => preg_replace('/\D/', '', $value) ?: $value,
            'dob' => $value,
            default => trim($value),
        };

        if ($column === 'firstname' || $column === 'lastname') {
            if ($normalized === '') {
                throw new RegistrationException('Value is required.', 422);
            }
        }

        if ($column === 'phone' && strlen($normalized) < 10) {
            throw new RegistrationException('Enter a valid phone number.', 422);
        }

        if ($column === 'dob' && ! strtotime($normalized)) {
            throw new RegistrationException('Enter a valid date.', 422);
        }

        $user->update([$column => $normalized]);
        $this->syncActiveCustomerApplication($user->fresh());

        return $this->progress($user->fresh());
    }

    public function storeDocument(User $user, string $documentType, UploadedFile $file): OnboardingDocument
    {
        $type = OnboardingDocumentType::from($documentType);
        $targetTier = config('onboarding.default_customer_target_tier', 'tier_2');
        $application = $this->ensureUpgradeApplication($user, AccountTier::from($targetTier));

        $max = config('onboarding.max_document_bytes');
        $blob = file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        if (! in_array($mime, $allowed, true)) {
            throw new RegistrationException('Only JPEG, PNG, WebP, and PDF files are allowed.', 422);
        }

        if (strlen($blob) > $max) {
            throw new RegistrationException('File exceeds maximum upload size.', 422);
        }

        $existing = OnboardingDocument::query()
            ->where('onboarding_application_id', $application->id)
            ->where('document_type', $type)
            ->first();

        if ($existing?->storage_path) {
            $this->documentStorage->delete($existing->storage_path);
        }

        $storagePath = $this->documentStorage->store(
            $application->id,
            $type->value,
            $blob,
            $mime,
            $file->getClientOriginalName(),
        );

        $doc = OnboardingDocument::query()->updateOrCreate(
            [
                'onboarding_application_id' => $application->id,
                'document_type' => $type,
            ],
            [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => strlen($blob),
                'storage_path' => $storagePath,
                'uploaded_by' => $user->id,
            ],
        );

        if ($type === OnboardingDocumentType::UtilityBill) {
            $user->kycVerifications()
                ->where('level', KycLevel::ProofOfAddress)
                ->update([
                    'status' => KycStatus::Submitted,
                    'submitted_at' => now(),
                ]);
        }

        $this->syncApplicationFromUser($user->fresh(), $application);

        return $doc;
    }

    public function syncActiveCustomerApplication(User $user): void
    {
        $targetTier = config('onboarding.default_customer_target_tier', 'tier_2');
        $application = OnboardingApplication::query()
            ->where('user_id', $user->id)
            ->where('applicant_type', ApplicantType::Customer)
            ->where('tier', $targetTier)
            ->whereNotIn('status', [OnboardingStatus::Rejected])
            ->latest()
            ->first();

        if ($application) {
            $this->syncApplicationFromUser($user->fresh(), $application);
        }
    }

    public function syncApplicationFromUser(User $user, OnboardingApplication $application): OnboardingApplication
    {
        if ($application->applicant_type !== ApplicantType::Customer) {
            return $application;
        }

        $user->loadMissing('kycVerifications');
        $identityKyc = $user->kycVerifications
            ->firstWhere('level', KycLevel::IdentityVerification);

        $kycPayload = $identityKyc?->payload ?? [];
        $entity = is_array($kycPayload['entity'] ?? null) ? $kycPayload['entity'] : [];

        $payload = $application->payload ?? [];
        if ($user->bvn) {
            $payload['bvn_verification'] = $this->buildVerificationSnapshot(
                'bvn',
                $user->bvn,
                $kycPayload,
                $entity,
                $user,
            );
        }
        if ($user->nin) {
            $payload['nin_verification'] = $this->buildVerificationSnapshot(
                'nin',
                $user->nin,
                $kycPayload,
                $entity,
                $user,
            );
        }

        $resolvedName = trim(implode(' ', array_filter([
            $entity['firstname'] ?? $user->firstname,
            $entity['middlename'] ?? null,
            $entity['lastname'] ?? $user->lastname,
        ])));

        $payload['identity_entity'] = $entity ?: null;
        $payload['date_of_birth'] = $user->dob?->format('Y-m-d')
            ?? ($entity['birthdate'] ?? null);
        $payload['resolved_name'] = $resolvedName !== '' ? strtoupper($resolvedName) : null;
        $payload['email'] = $entity['email'] ?? $user->email;

        $hasIdentity = ! empty($user->bvn) || ! empty($user->nin);
        $verificationStatus = $hasIdentity
            ? VerificationCheckStatus::Verified
            : VerificationCheckStatus::Pending;

        $application->update([
            'business_name' => $user->full_name ?: $application->business_name,
            'proprietor_name' => $user->full_name ?: $application->proprietor_name,
            'bvn_masked' => $user->bvn ? $this->maskId($user->bvn) : $application->bvn_masked,
            'nin_masked' => $user->nin ? $this->maskId($user->nin) : $application->nin_masked,
            'phone' => $user->phone ?? ($entity['telephone'] ?? $application->phone),
            'location' => $application->location ?? ($entity['address'] ?? null),
            'payload' => $payload,
            'verification_status' => $verificationStatus,
        ]);

        return $application->fresh(['documents']);
    }

    /**
     * @param  array<string, mixed>  $kycPayload
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>
     */
    protected function buildVerificationSnapshot(
        string $column,
        string $value,
        array $kycPayload,
        array $entity,
        User $user,
    ): array {
        $storedValue = $kycPayload[$column] ?? null;
        $matches = $storedValue === null || $storedValue === $value;

        $resolvedName = trim(implode(' ', array_filter([
            $entity['firstname'] ?? $user->firstname,
            $entity['middlename'] ?? null,
            $entity['lastname'] ?? $user->lastname,
        ])));

        return [
            'valid' => $matches,
            'resolved_name' => $resolvedName !== '' ? strtoupper($resolvedName) : null,
            'message' => $matches ? 'Verified via Swwipe' : 'Identity record mismatch',
            'provider' => $kycPayload['provider'] ?? 'swwipe',
            'verified_at' => $kycPayload['verified_at'] ?? null,
        ];
    }

    protected function maskId(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?: $value;

        return '****'.substr($digits, -4);
    }

    /**
     * @return array<string, mixed>
     */
    public function submitForReview(User $user, ?string $targetTier = null): array
    {
        $targetTier = $targetTier ?? config('onboarding.default_customer_target_tier', 'tier_2');
        $application = $this->ensureUpgradeApplication($user, AccountTier::from($targetTier));
        $progress = $this->progress($user, $targetTier);

        if (! $progress['ready_to_submit']) {
            throw new RegistrationException('Complete all required identity checks and documents before submitting.', 422);
        }

        $this->syncApplicationFromUser($user->fresh(), $application);
        $application->refresh();

        if ($application->status === OnboardingStatus::Draft) {
            $application->update([
                'status' => OnboardingStatus::PendingReview,
                'submitted_at' => now(),
                'channel' => OnboardingChannel::Mobile,
            ]);
        } elseif ($application->status === OnboardingStatus::Queried) {
            $application->update([
                'status' => OnboardingStatus::PendingReview,
                'submitted_at' => now(),
            ]);
        }

        return $this->progress($user->fresh(), $targetTier);
    }

    protected function ensureUpgradeApplication(User $user, AccountTier $targetTier): OnboardingApplication
    {
        $existing = OnboardingApplication::query()
            ->where('user_id', $user->id)
            ->where('applicant_type', ApplicantType::Customer)
            ->where('tier', $targetTier)
            ->whereNotIn('status', [OnboardingStatus::Rejected])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->dismissEmptyRegistrationStub($user);

        return $this->onboardingService->createFromMobileCustomer($user, [
            'tier' => $targetTier,
            'status' => OnboardingStatus::Draft,
            'submitted_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $req
     * @return array<string, mixed>
     */
    protected function formatRequirements(string $applicantType, string $tier, array $req): array
    {
        $identity = $req['identity_any_of'] ?? [];
        $documents = $req['documents'] ?? [];

        return [
            'applicant_type' => $applicantType,
            'tier' => $tier,
            'label' => $req['label'] ?? $tier,
            'description' => $req['description'] ?? '',
            'identity' => collect($identity)->map(fn ($id) => [
                'key' => $id,
                'label' => match ($id) {
                    'bvn' => 'BVN verification',
                    'nin' => 'NIN verification',
                    default => $id,
                },
            ])->values()->all(),
            'documents' => collect($documents)->map(fn ($key) => [
                'key' => $key,
                'label' => config("onboarding.document_types.{$key}", $key),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function completedSteps(User $user, OnboardingApplication $application): array
    {
        $identity = $user->kycVerifications()
            ->where('level', KycLevel::IdentityVerification)
            ->whereIn('status', [KycStatus::Submitted, KycStatus::Approved])
            ->exists();

        $uploaded = $application->documents()->pluck('document_type')->map(fn ($t) => $t->value)->all();

        return [
            'identity' => $identity || ! empty($user->bvn) || ! empty($user->nin),
            'bvn' => ! empty($user->bvn),
            'nin' => ! empty($user->nin),
            'documents' => $uploaded,
        ];
    }

    /**
     * @param  array<string, mixed>  $requirements
     * @param  array<string, mixed>  $completed
     */
    protected function isReadyToSubmit(array $requirements, array $completed): bool
    {
        if (! $completed['identity']) {
            return false;
        }

        $identityKeys = collect($requirements['identity'])->pluck('key')->all();
        if ($identityKeys !== []) {
            $hasIdentity = false;
            foreach ($identityKeys as $key) {
                if (! empty($completed[$key])) {
                    $hasIdentity = true;
                    break;
                }
            }
            if (! $hasIdentity) {
                return false;
            }
        }

        foreach ($requirements['documents'] as $doc) {
            if (! in_array($doc['key'], $completed['documents'], true)) {
                return false;
            }
        }

        return true;
    }

    protected function resolveKycStatus(User $user, OnboardingApplication $application): string
    {
        if ($application->status === OnboardingStatus::Approved) {
            return 'verified';
        }

        $hasApprovedCustomerApplication = OnboardingApplication::query()
            ->where('user_id', $user->id)
            ->where('applicant_type', ApplicantType::Customer)
            ->where('status', OnboardingStatus::Approved)
            ->exists();

        if ($hasApprovedCustomerApplication && $user->status === UserStatus::Approved) {
            return 'verified';
        }
        if (in_array($application->status, [OnboardingStatus::PendingReview, OnboardingStatus::Submitted, OnboardingStatus::OnHold], true)) {
            return 'pending';
        }
        if ($application->status === OnboardingStatus::Queried) {
            return 'pending';
        }
        if ($application->status === OnboardingStatus::Rejected) {
            return 'rejected';
        }

        return $user->status === UserStatus::Approved ? 'verified' : 'not_started';
    }

    protected function dismissEmptyRegistrationStub(User $user): void
    {
        OnboardingApplication::query()
            ->where('user_id', $user->id)
            ->where('applicant_type', ApplicantType::Customer)
            ->where('tier', AccountTier::Tier1)
            ->where('status', OnboardingStatus::PendingReview)
            ->whereNull('bvn_masked')
            ->whereNull('nin_masked')
            ->whereDoesntHave('documents')
            ->update([
                'status' => OnboardingStatus::Rejected,
                'rejection_reason' => 'Superseded by tier upgrade application.',
                'reviewed_at' => now(),
            ]);
    }
}
