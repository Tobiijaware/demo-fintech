<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\TopUpAgentFloatRequest;
use App\Http\Requests\Admin\UpdateAgentRequest;
use App\Models\Agent;
use App\Services\Agents\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends ApiController
{
    public function __construct(
        private AgentService $service,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'region', 'hub', 'search', 'per_page',
        ]));

        $paginator->getCollection()->transform(fn (Agent $agent) => $this->format($agent));

        return $this->success($paginator);
    }

    public function show(Agent $agent): JsonResponse
    {
        return $this->success($this->format($this->service->find($agent->id), true));
    }

    public function update(UpdateAgentRequest $request, Agent $agent): JsonResponse
    {
        $updated = $this->service->update($agent, $request->validated(), auth('api')->user());

        return $this->success($this->format($updated, true), 'Agent updated.');
    }

    public function topUpFloat(TopUpAgentFloatRequest $request, Agent $agent): JsonResponse
    {
        $updated = $this->service->topUpFloat($agent, $request->validated(), auth('api')->user());

        return $this->success($this->format($updated, true), 'Agent float topped up.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(Agent $agent, bool $detailed = false): array
    {
        $data = [
            'id' => $agent->id,
            'code' => $agent->code,
            'business_name' => $agent->business_name,
            'proprietor_name' => $agent->proprietor_name,
            'location' => $agent->location,
            'cac_number' => $agent->cac_number,
            'tier' => $agent->tier->value,
            'tier_label' => $agent->tier->label(),
            'status' => $agent->status->value,
            'region' => $agent->region,
            'hub' => $agent->hub,
            'float_balance' => (float) $agent->float_balance,
            'terminals_count' => $agent->terminals_count ?? $agent->terminals()->count(),
            'onboarding_application_id' => $agent->onboarding_application_id,
            'user_id' => $agent->user_id,
            'created_at' => $agent->created_at?->toIso8601String(),
            'updated_at' => $agent->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['metadata'] = $agent->metadata;
            $data['onboarding_application'] = $agent->relationLoaded('onboardingApplication') && $agent->onboardingApplication
                ? [
                    'id' => $agent->onboardingApplication->id,
                    'reference' => $agent->onboardingApplication->reference,
                    'status' => $agent->onboardingApplication->status->value,
                ]
                : null;
            $data['user'] = $agent->relationLoaded('user') && $agent->user
                ? ['id' => $agent->user->id, 'name' => $agent->user->full_name, 'email' => $agent->user->email]
                : null;
            $data['terminals'] = $agent->relationLoaded('terminals')
                ? $agent->terminals->map(fn ($terminal) => [
                    'id' => $terminal->id,
                    'serial_number' => $terminal->serial_number,
                    'model' => $terminal->model,
                    'status' => $terminal->status->value,
                    'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
                ])
                : [];
        }

        return $data;
    }
}
