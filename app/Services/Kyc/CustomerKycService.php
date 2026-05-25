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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function tierRequirements(string $applicantType, string $targetTier): array
    {
        $req = config("onboarding.tier_requirements.{$applicantType}.{$targetTier}");

        if (! $req) {
            throw new InvalidArgumentException('Unknown tier requirements.');
        }

        return $this->formatRequirements($applicantType, $targetTier, $req);
    }

    /**
     * @return array<string, mixed>
     */
    public function allTierDefinitions(string $applicantType = 'customer'): array
    {
        $tiers = config("onboarding.tier_requirements.{$applicantType}", []);

        return collect($tiers)->map(function ($req, $tier) use ($applicantType) {
            return $this->formatRequirements($applicantType, $tier, $req);
        })->values()->all();
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
        $completed = $this->completedSteps($user, $application);

        $ready = $this->isReadyToSubmit($requirements, $completed);

        return [
            'account_tier' => $currentTier,
            'target_tier' => $targetTier,
            'kyc_status' => $this->resolveKycStatus($user, $application),
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

        return $doc;
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
}
