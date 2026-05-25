<?php

namespace Database\Seeders;

use App\Enums\AccountTier;
use App\Enums\AgentStatus;
use App\Enums\AgentTerminalStatus;
use App\Enums\ApplicantType;
use App\Enums\OnboardingStatus;
use App\Models\Agent;
use App\Models\AgentTerminal;
use App\Models\OnboardingApplication;
use App\Services\Agents\AgentProvisioningService;
use Illuminate\Database\Seeder;

class AgentsSeeder extends Seeder
{
    public function run(): void
    {
        $provisioning = app(AgentProvisioningService::class);

        OnboardingApplication::query()
            ->where('applicant_type', ApplicantType::Agent)
            ->where('status', OnboardingStatus::Approved)
            ->whereDoesntHave('agent')
            ->each(fn (OnboardingApplication $app) => $provisioning->provisionFromOnboarding($app));

        if (Agent::query()->exists()) {
            return;
        }

        $samples = [
            [
                'code' => 'AGT-001',
                'business_name' => 'Mama Ngozi Provisions',
                'proprietor_name' => 'Ngozi Okwuosa',
                'location' => 'Onitsha main market',
                'cac_number' => 'CAC RC 9921044',
                'tier' => AccountTier::Tier3,
                'status' => AgentStatus::Active,
                'region' => 'Onitsha',
                'hub' => 'South-East Hub',
                'float_balance' => 250000.00,
                'terminals' => [
                    ['serial_number' => 'POS-NGO-001', 'model' => 'PAX A920', 'status' => AgentTerminalStatus::Active],
                    ['serial_number' => 'POS-NGO-002', 'model' => 'PAX A920', 'status' => AgentTerminalStatus::Inactive],
                ],
            ],
            [
                'code' => 'AGT-002',
                'business_name' => 'Swift Pay Agency',
                'proprietor_name' => 'Ibrahim Musa',
                'location' => 'Lagos',
                'tier' => AccountTier::Tier2,
                'status' => AgentStatus::Active,
                'region' => 'Lagos',
                'hub' => 'Lagos Central Hub',
                'float_balance' => 180000.00,
                'terminals' => [
                    ['serial_number' => 'POS-SWP-001', 'model' => 'Verifone V240m', 'status' => AgentTerminalStatus::Active],
                ],
            ],
            [
                'code' => 'AGT-003',
                'business_name' => 'Delta Cash Point',
                'proprietor_name' => 'Paul Edet',
                'location' => 'Warri',
                'tier' => AccountTier::Tier2,
                'status' => AgentStatus::Pending,
                'region' => 'Warri',
                'hub' => null,
                'float_balance' => 0,
                'terminals' => [],
            ],
        ];

        foreach ($samples as $sample) {
            $terminals = $sample['terminals'];
            unset($sample['terminals']);

            $agent = Agent::query()->updateOrCreate(
                ['code' => $sample['code']],
                $sample,
            );

            foreach ($terminals as $terminal) {
                AgentTerminal::query()->updateOrCreate(
                    ['serial_number' => $terminal['serial_number']],
                    array_merge($terminal, ['agent_id' => $agent->id]),
                );
            }
        }
    }
}
