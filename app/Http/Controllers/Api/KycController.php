<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Kyc\ValidateBvnRequest;
use App\Http\Requests\Kyc\ValidateNinRequest;
use App\Services\Kyc\IdentityVerificationService;
use Illuminate\Http\JsonResponse;

class KycController extends ApiController
{
    public function __construct(private IdentityVerificationService $identityVerificationService) {}

    public function validateBvn(ValidateBvnRequest $request): JsonResponse
    {
        $result = $this->identityVerificationService->validateBvn(
            auth('api')->user(),
            $request->validated('bvn'),
        );

        return $this->success($result, 'BVN verified successfully.');
    }

    public function validateNin(ValidateNinRequest $request): JsonResponse
    {
        $result = $this->identityVerificationService->validateNin(
            auth('api')->user(),
            $request->validated('nin'),
        );

        return $this->success($result, 'NIN verified successfully.');
    }
}
