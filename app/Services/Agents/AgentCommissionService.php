<?php

namespace App\Services\Agents;

use App\Models\AgentCommission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AgentCommissionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = AgentCommission::query()
            ->with(['agent:id,code,business_name,region,status'])
            ->orderByDesc('period')
            ->orderByDesc('id');

        if (! empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }
}
