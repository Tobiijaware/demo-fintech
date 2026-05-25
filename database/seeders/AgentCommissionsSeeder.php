<?php

namespace Database\Seeders;

use App\Enums\AgentCommissionStatus;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AgentCommissionsSeeder extends Seeder
{
    public function run(): void
    {
        $agents = Agent::query()->orderBy('id')->get();

        if ($agents->isEmpty()) {
            return;
        }

        $periods = [
            now()->subMonth()->format('Y-m'),
            now()->format('Y-m'),
        ];

        foreach ($agents as $index => $agent) {
            foreach ($periods as $periodIndex => $period) {
                $gross = round((float) $agent->float_balance * (1.8 + $periodIndex * 0.4) + ($index + 1) * 12500, 2);
                $commission = round($gross * 0.008, 2);
                $status = $periodIndex === 0 ? AgentCommissionStatus::Paid : AgentCommissionStatus::Accrued;

                AgentCommission::query()->updateOrCreate(
                    ['agent_id' => $agent->id, 'period' => $period],
                    [
                        'gross_volume' => $gross,
                        'commission_amount' => $commission,
                        'status' => $status,
                        'paid_at' => $status === AgentCommissionStatus::Paid
                            ? Carbon::parse($period.'-28')->endOfDay()
                            : null,
                    ],
                );
            }
        }

        $this->linkSampleTransactions($agents);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Agent>  $agents
     */
    private function linkSampleTransactions($agents): void
    {
        $agentIds = $agents->pluck('id')->all();

        Transaction::query()
            ->whereNull('agent_id')
            ->orderByDesc('created_at')
            ->limit(24)
            ->get()
            ->each(function (Transaction $transaction, int $index) use ($agentIds) {
                $transaction->update([
                    'agent_id' => $agentIds[$index % count($agentIds)],
                ]);
            });
    }
}
