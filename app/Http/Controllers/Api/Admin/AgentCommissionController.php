<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AgentCommission;
use App\Services\Agents\AgentCommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentCommissionController extends ApiController
{
    public function __construct(
        private AgentCommissionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only(['period', 'per_page']));

        $paginator->getCollection()->transform(fn (AgentCommission $commission) => $this->format($commission));

        return $this->success($paginator);
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(AgentCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'period' => $commission->period,
            'gross_volume' => (float) $commission->gross_volume,
            'commission_amount' => (float) $commission->commission_amount,
            'status' => $commission->status->value,
            'paid_at' => $commission->paid_at?->toIso8601String(),
            'created_at' => $commission->created_at?->toIso8601String(),
            'agent' => $commission->relationLoaded('agent') && $commission->agent
                ? [
                    'id' => $commission->agent->id,
                    'code' => $commission->agent->code,
                    'business_name' => $commission->agent->business_name,
                    'region' => $commission->agent->region,
                    'status' => $commission->agent->status->value,
                ]
                : null,
        ];
    }
}
