<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreAgentTerminalRequest;
use App\Http\Requests\Admin\UpdateAgentTerminalRequest;
use App\Models\Agent;
use App\Models\AgentTerminal;
use App\Services\Agents\AgentTerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentTerminalController extends ApiController
{
    public function __construct(
        private AgentTerminalService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only(['status', 'search', 'per_page']));

        $paginator->getCollection()->transform(fn (AgentTerminal $terminal) => $this->format($terminal));

        return $this->success($paginator);
    }

    public function store(StoreAgentTerminalRequest $request, Agent $agent): JsonResponse
    {
        $terminal = $this->service->assign(
            $agent,
            $request->validated(),
            auth('api')->user(),
        );

        return $this->success($this->format($terminal), 'Terminal assigned.', 201);
    }

    public function update(UpdateAgentTerminalRequest $request, AgentTerminal $terminal): JsonResponse
    {
        $updated = $this->service->update(
            $terminal,
            $request->validated(),
            auth('api')->user(),
        );

        return $this->success($this->format($updated), 'Terminal updated.');
    }

    public function destroy(AgentTerminal $terminal): JsonResponse
    {
        $this->service->delete($terminal, auth('api')->user());

        return $this->success(null, 'Terminal removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(AgentTerminal $terminal): array
    {
        return [
            'id' => $terminal->id,
            'serial_number' => $terminal->serial_number,
            'model' => $terminal->model,
            'status' => $terminal->status->value,
            'last_seen_at' => $terminal->last_seen_at?->toIso8601String(),
            'created_at' => $terminal->created_at?->toIso8601String(),
            'updated_at' => $terminal->updated_at?->toIso8601String(),
            'agent' => $terminal->relationLoaded('agent') && $terminal->agent
                ? [
                    'id' => $terminal->agent->id,
                    'code' => $terminal->agent->code,
                    'business_name' => $terminal->agent->business_name,
                    'status' => $terminal->agent->status->value,
                ]
                : null,
        ];
    }
}
