<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BackofficeRbacSeeder::class,
            TierDefinitionSeeder::class,
            MakerCheckerPolicySeeder::class,
            DemoWalletSeeder::class,
            OnboardingApplicationsSeeder::class,
            AgentsSeeder::class,
            AgentCommissionsSeeder::class,
            TreasurySeeder::class,
            SupportSeeder::class,
            AuditLogSeeder::class,
            OperationsSeeder::class,
            SettlementSeeder::class,
            ComplianceSeeder::class,
            AmlSeeder::class,
            SystemSettingsSeeder::class,
            ProvisioningRequestSeeder::class,
            StaffSessionSeeder::class,
        ]);
    }
}
