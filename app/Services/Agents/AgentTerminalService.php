<?php

namespace App\Services\Agents;

use App\Enums\AgentTerminalStatus;
use App\Models\Agent;
use App\Models\AgentTerminal;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AgentTerminalService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AgentTerminal::query()
            ->with(['agent:id,code,business_name,status'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('agent', function ($agentQuery) use ($search) {
                        $agentQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(Agent $agent, array $data, User $actor): AgentTerminal
    {
        $terminal = $agent->terminals()->create([
            'serial_number' => $data['serial_number'],
            'model' => $data['model'] ?? null,
            'status' => AgentTerminalStatus::Active,
        ]);

        $this->auditLog->record(
            $actor,
            'agent.terminal.created',
            'AgentTerminal',
            (string) $terminal->id,
            "Assigned terminal {$terminal->serial_number} to agent {$agent->code}",
            [
                'agent_id' => $agent->id,
                'agent_code' => $agent->code,
                'serial_number' => $terminal->serial_number,
                'model' => $terminal->model,
            ],
        );

        return $terminal->load('agent:id,code,business_name,status');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AgentTerminal $terminal, array $data, User $actor): AgentTerminal
    {
        $before = [
            'status' => $terminal->status->value,
            'model' => $terminal->model,
        ];

        $terminal->update($data);

        $updated = $terminal->fresh(['agent:id,code,business_name,status']);

        $this->auditLog->record(
            $actor,
            'agent.terminal.updated',
            'AgentTerminal',
            (string) $updated->id,
            "Updated terminal {$updated->serial_number}",
            [
                'agent_id' => $updated->agent_id,
                'serial_number' => $updated->serial_number,
                'before' => $before,
                'after' => [
                    'status' => $updated->status->value,
                    'model' => $updated->model,
                ],
            ],
        );

        return $updated;
    }

    public function delete(AgentTerminal $terminal, User $actor): void
    {
        $agentCode = $terminal->agent?->code;
        $serial = $terminal->serial_number;
        $agentId = $terminal->agent_id;
        $terminalId = $terminal->id;

        $terminal->delete();

        $this->auditLog->record(
            $actor,
            'agent.terminal.deleted',
            'AgentTerminal',
            (string) $terminalId,
            "Removed terminal {$serial} from agent {$agentCode}",
            [
                'agent_id' => $agentId,
                'serial_number' => $serial,
            ],
        );
    }
}
