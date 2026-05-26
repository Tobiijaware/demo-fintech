<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreTierCriterionRequest;
use App\Http\Requests\Admin\UpdateTierCriterionRequest;
use App\Http\Requests\Admin\UpdateTierDefinitionRequest;
use App\Models\TierCriterion;
use App\Models\TierDefinition;
use App\Services\Kyc\CustomerKycService;
use App\Services\Kyc\TierCriteriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TierDefinitionController extends ApiController
{
    public function __construct(
        private TierCriteriaService $tierCriteriaService,
        private CustomerKycService $customerKycService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $applicantType = $request->query('applicant_type', 'customer');
        $tier = $request->query('tier');

        if ($tier) {
            return $this->success(
                $this->customerKycService->tierRequirements($applicantType, $tier),
            );
        }

        return $this->success(
            $this->customerKycService->allTierDefinitions($applicantType),
        );
    }

    public function update(UpdateTierDefinitionRequest $request, TierDefinition $tierDefinition): JsonResponse
    {
        $updated = $this->tierCriteriaService->updateDefinition(
            $tierDefinition,
            $request->validated(),
        );

        return $this->success(
            $this->tierCriteriaService->formatDefinition($updated),
            'Tier definition updated.',
        );
    }

    public function storeCriterion(
        StoreTierCriterionRequest $request,
        TierDefinition $tierDefinition,
    ): JsonResponse {
        $criterion = $this->tierCriteriaService->upsertCriterion(
            $tierDefinition,
            $request->validated(),
        );

        return $this->success(
            $this->tierCriteriaService->formatCriterion($criterion),
            'Criterion added.',
            201,
        );
    }

    public function updateCriterion(
        UpdateTierCriterionRequest $request,
        TierCriterion $tierCriterion,
    ): JsonResponse {
        $updated = $this->tierCriteriaService->upsertCriterion(
            $tierCriterion->tierDefinition,
            $request->validated(),
            $tierCriterion,
        );

        return $this->success(
            $this->tierCriteriaService->formatCriterion($updated),
            'Criterion updated.',
        );
    }

    public function destroyCriterion(TierCriterion $tierCriterion): JsonResponse
    {
        $this->tierCriteriaService->deleteCriterion($tierCriterion);

        return $this->success(null, 'Criterion removed.');
    }
}
