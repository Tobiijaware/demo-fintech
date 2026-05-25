<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\DecisionOnboardingRequest;
use App\Http\Requests\Admin\StoreOnboardingApplicationRequest;
use App\Models\OnboardingApplication;
use App\Services\Kyc\CustomerKycService;
use App\Services\Onboarding\OnboardingApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingApplicationController extends ApiController
{
    public function __construct(
        private OnboardingApplicationService $service,
        private CustomerKycService $customerKycService,
    ) {}

    public function tierDefinitions(Request $request): JsonResponse
    {
        $applicantType = $request->query('applicant_type', 'agent');
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

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'queue', 'tier', 'applicant_type', 'verification_status', 'per_page',
        ]));

        $paginator->getCollection()->transform(fn (OnboardingApplication $app) => $this->format($app));

        return $this->success($paginator);
    }

    public function show(OnboardingApplication $onboardingApplication): JsonResponse
    {
        $app = $this->service->find($onboardingApplication->id);
        if ($app->applicant_type->value === 'customer' && $app->user_id) {
            $app = $this->customerKycService->syncApplicationFromUser($app->user, $app);
            $app = $this->service->find($app->id);
        }

        return $this->success($this->format($app, true));
    }

    public function store(StoreOnboardingApplicationRequest $request): JsonResponse
    {
        $app = $this->service->createInternal($request->validated(), auth('api')->user());
        $app = $this->service->submit($app, auth('api')->user());

        return $this->success($this->format($app->fresh(['maker'])), 'Application submitted for review.', 201);
    }

    public function submit(OnboardingApplication $onboardingApplication): JsonResponse
    {
        $app = $this->service->submit($onboardingApplication, auth('api')->user());

        return $this->success($this->format($app), 'Submitted for checker review.');
    }

    public function approve(OnboardingApplication $onboardingApplication): JsonResponse
    {
        $app = $this->service->approve($onboardingApplication, auth('api')->user());

        return $this->success($this->format($app), 'Application approved.');
    }

    public function reject(OnboardingApplication $onboardingApplication, DecisionOnboardingRequest $request): JsonResponse
    {
        $app = $this->service->reject($onboardingApplication, auth('api')->user(), $request->validated('reason'));

        return $this->success($this->format($app), 'Application rejected.');
    }

    public function query(OnboardingApplication $onboardingApplication, DecisionOnboardingRequest $request): JsonResponse
    {
        $app = $this->service->queryApplicant($onboardingApplication, auth('api')->user(), $request->validated('reason'));

        return $this->success($this->format($app), 'Applicant queried.');
    }

    public function hold(OnboardingApplication $onboardingApplication, Request $request): JsonResponse
    {
        $app = $this->service->hold($onboardingApplication, auth('api')->user(), $request->input('reason'));

        return $this->success($this->format($app), 'Application placed on hold.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(OnboardingApplication $app, bool $detailed = false): array
    {
        $data = [
            'id' => $app->id,
            'reference' => $app->reference,
            'applicant_type' => $app->applicant_type->value,
            'tier' => $app->tier->value,
            'tier_label' => $app->tier->label(),
            'status' => $app->status->value,
            'channel' => $app->channel->value,
            'verification_status' => $app->verification_status->value,
            'business_name' => $app->business_name,
            'proprietor_name' => $app->proprietor_name,
            'location' => $app->location,
            'cac_number' => $app->cac_number,
            'business_type' => $app->business_type,
            'bvn_masked' => $app->bvn_masked,
            'nin_masked' => $app->nin_masked,
            'phone' => $app->phone,
            'estimated_settlement' => $app->estimated_settlement,
            'linked_agents' => $app->linked_agents,
            'submitted_at' => $app->submitted_at?->toIso8601String(),
            'reviewed_at' => $app->reviewed_at?->toIso8601String(),
            'sla_due_at' => $app->sla_due_at?->toIso8601String(),
            'age_label' => $app->submitted_at?->diffForHumans(short: true),
            'rejection_reason' => $app->rejection_reason,
            'query_notes' => $app->query_notes,
            'user_id' => $app->user_id,
            'maker' => $app->maker ? ['id' => $app->maker->id, 'name' => $app->maker->full_name] : null,
            'checker' => $app->checker ? ['id' => $app->checker->id, 'name' => $app->checker->full_name] : null,
            'documents' => $app->relationLoaded('documents')
                ? $app->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'document_type' => $d->document_type->value,
                    'document_type_label' => $d->document_type->label(),
                    'original_filename' => $d->original_filename,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                    'is_image' => $d->isImage(),
                    'is_pdf' => $d->isPdf(),
                    'view_url' => url("/api/v1/admin/onboarding/documents/{$d->id}/file"),
                ])
                : [],
            'required_documents' => $this->requiredDocumentsFor($app),
            'payload' => $app->payload,
        ];

        if ($detailed && $app->relationLoaded('events')) {
            $data['events'] = $app->events->map(fn ($e) => [
                'action' => $e->action,
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'notes' => $e->notes,
                'actor' => $e->actor?->full_name,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function requiredDocumentsFor(OnboardingApplication $app): array
    {
        $tierReq = config("onboarding.tier_requirements.{$app->applicant_type->value}.{$app->tier->value}.documents");

        return $tierReq ?? config("onboarding.required_documents.{$app->applicant_type->value}", []);
    }
}
