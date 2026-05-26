<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Kyc\SaveKycFieldRequest;
use App\Http\Requests\Kyc\StoreKycDocumentRequest;
use App\Http\Requests\Kyc\ValidateBvnRequest;
use App\Http\Requests\Kyc\ValidateNinRequest;
use App\Services\Kyc\CustomerKycService;
use App\Services\Kyc\IdentityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends ApiController
{
    public function __construct(
        private IdentityVerificationService $identityVerificationService,
        private CustomerKycService $customerKycService,
    ) {}

    public function tierRequirements(Request $request): JsonResponse
    {
        $applicantType = $request->query('applicant_type', 'customer');
        $tier = $request->query('tier', config('onboarding.default_customer_target_tier', 'tier_2'));

        return $this->success($this->customerKycService->tierRequirements($applicantType, $tier));
    }

    public function tierDefinitions(Request $request): JsonResponse
    {
        $applicantType = $request->query('applicant_type', 'customer');

        return $this->success($this->customerKycService->allTierDefinitions($applicantType));
    }

    public function progress(Request $request): JsonResponse
    {
        $targetTier = $request->query('target_tier');

        return $this->success(
            $this->customerKycService->progress(auth('api')->user(), $targetTier),
        );
    }

    public function validateBvn(ValidateBvnRequest $request): JsonResponse
    {
        $result = $this->identityVerificationService->validateBvn(
            auth('api')->user(),
            $request->validated('bvn'),
            $request->validated('firstname'),
            $request->validated('lastname'),
            $request->validated('date_of_birth'),
        );

        $this->customerKycService->syncActiveCustomerApplication(auth('api')->user());

        return $this->success($result, 'BVN verified successfully.');
    }

    public function validateNin(ValidateNinRequest $request): JsonResponse
    {
        $result = $this->identityVerificationService->validateNin(
            auth('api')->user(),
            $request->validated('nin'),
            $request->validated('phone'),
            $request->validated('date_of_birth'),
        );

        $this->customerKycService->syncActiveCustomerApplication(auth('api')->user());

        return $this->success($result, 'NIN verified successfully.');
    }

    public function saveField(SaveKycFieldRequest $request, string $key): JsonResponse
    {
        $progress = $this->customerKycService->saveField(
            auth('api')->user(),
            $key,
            $request->validated('value'),
        );

        return $this->success($progress, 'Field saved.');
    }

    public function storeDocument(StoreKycDocumentRequest $request): JsonResponse
    {
        $doc = $this->customerKycService->storeDocument(
            auth('api')->user(),
            $request->validated('document_type'),
            $request->file('file'),
        );

        $this->customerKycService->syncActiveCustomerApplication(auth('api')->user());

        return $this->success([
            'id' => $doc->id,
            'document_type' => $doc->document_type->value,
            'original_filename' => $doc->original_filename,
        ], 'Document uploaded.');
    }

    public function submit(Request $request): JsonResponse
    {
        $progress = $this->customerKycService->submitForReview(
            auth('api')->user(),
            $request->input('target_tier'),
        );

        return $this->success($progress, 'Upgrade submitted for compliance review.');
    }
}
