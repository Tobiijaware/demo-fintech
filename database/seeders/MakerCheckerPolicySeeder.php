<?php

namespace Database\Seeders;

use App\Models\MakerCheckerPolicy;
use Illuminate\Database\Seeder;

class MakerCheckerPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            [
                'id' => 'mc-onboard-agent',
                'department' => 'Onboarding',
                'action' => 'Internal agent onboarding',
                'description' => 'An agent manager or KYC officer can create an internal onboarding draft, but a different officer must approve before the agent goes live.',
                'resource' => 'agent_records',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'live',
                'sort_order' => 10,
                'role_pairs' => [
                    [
                        'id' => 'mc-onboard-agent-p1',
                        'label' => 'Agent manager initiates',
                        'maker_roles' => ['agent_manager'],
                        'checker_roles' => ['kyc_officer', 'compliance_officer'],
                    ],
                    [
                        'id' => 'mc-onboard-agent-p2',
                        'label' => 'KYC officer initiates',
                        'maker_roles' => ['kyc_officer'],
                        'checker_roles' => ['compliance_officer'],
                    ],
                ],
            ],
            [
                'id' => 'mc-kyc-tier3',
                'department' => 'Onboarding',
                'action' => 'Tier 3 KYC approval',
                'description' => 'Tier 1–2 approvals stay with the reviewing KYC officer. Tier 3 upgrades require a senior KYC officer or compliance sign-off.',
                'resource' => 'kyc_applications',
                'threshold' => 'Tier 3 only',
                'enforced' => true,
                'enforcement' => 'live',
                'sort_order' => 20,
                'role_pairs' => [
                    [
                        'id' => 'mc-kyc-tier3-p1',
                        'label' => 'Tier 3 escalation',
                        'maker_roles' => ['kyc_officer'],
                        'checker_roles' => ['compliance_officer'],
                    ],
                ],
            ],
            [
                'id' => 'mc-reversal',
                'department' => 'Operations',
                'action' => 'Customer reversal request',
                'description' => 'Support can initiate a reversal, but settlement must approve before funds move. Initiator cannot approve their own case.',
                'resource' => 'reversals',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'live',
                'sort_order' => 30,
                'role_pairs' => [
                    [
                        'id' => 'mc-reversal-p1',
                        'label' => 'Support initiates',
                        'maker_roles' => ['customer_support'],
                        'checker_roles' => ['settlement_officer', 'finance_treasury'],
                    ],
                    [
                        'id' => 'mc-reversal-p2',
                        'label' => 'Operations initiates',
                        'maker_roles' => ['operations_lead'],
                        'checker_roles' => ['settlement_officer'],
                    ],
                ],
            ],
            [
                'id' => 'mc-settlement-credit',
                'department' => 'Finance',
                'action' => 'Manual wallet credit',
                'description' => 'Settlement officers can post manual credits up to ₦1M. Amounts above the threshold need treasury checker approval.',
                'resource' => 'settlement_recon',
                'threshold' => 'Above ₦1M',
                'enforced' => true,
                'enforcement' => 'policy',
                'sort_order' => 40,
                'role_pairs' => [
                    [
                        'id' => 'mc-settlement-credit-p1',
                        'label' => 'High-value credit',
                        'maker_roles' => ['settlement_officer'],
                        'checker_roles' => ['finance_treasury', 'super_admin'],
                    ],
                ],
            ],
            [
                'id' => 'mc-str-filing',
                'department' => 'Risk & control',
                'action' => 'STR transmission',
                'description' => 'AML analysts draft suspicious transaction reports. Compliance must review and submit to NFIU — dual write before transmission.',
                'resource' => 'str_filings',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'live',
                'sort_order' => 50,
                'role_pairs' => [
                    [
                        'id' => 'mc-str-filing-p1',
                        'label' => 'Standard STR path',
                        'maker_roles' => ['aml_analyst'],
                        'checker_roles' => ['compliance_officer'],
                    ],
                ],
            ],
            [
                'id' => 'mc-role-change',
                'department' => 'Governance',
                'action' => 'Role or permission change',
                'description' => 'Super admins can propose role updates, but a second super admin must approve before permissions take effect.',
                'resource' => 'user_management',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'policy',
                'sort_order' => 60,
                'role_pairs' => [
                    [
                        'id' => 'mc-role-change-p1',
                        'label' => 'Dual super admin',
                        'maker_roles' => ['super_admin'],
                        'checker_roles' => ['super_admin'],
                    ],
                ],
            ],
            [
                'id' => 'mc-float-adjustment',
                'department' => 'Finance',
                'action' => 'Float top-up or payout',
                'description' => 'Treasury initiates float adjustments. A different finance lead must approve before balances change.',
                'resource' => 'float_positions',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'policy',
                'sort_order' => 65,
                'role_pairs' => [
                    [
                        'id' => 'mc-float-adjustment-p1',
                        'label' => 'Treasury float adjustment',
                        'maker_roles' => ['finance_treasury'],
                        'checker_roles' => ['finance_treasury', 'super_admin'],
                    ],
                ],
            ],
            [
                'id' => 'mc-commission-payout',
                'department' => 'Finance',
                'action' => 'Commission batch payout',
                'description' => 'Treasury prepares commission batches. A different finance lead approves release — maker cannot sign off their own batch.',
                'resource' => 'commission_payout',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'policy',
                'sort_order' => 70,
                'role_pairs' => [
                    [
                        'id' => 'mc-commission-payout-p1',
                        'label' => 'Treasury batch',
                        'maker_roles' => ['finance_treasury'],
                        'checker_roles' => ['finance_treasury', 'super_admin'],
                    ],
                ],
            ],
            [
                'id' => 'mc-wallet-freeze',
                'department' => 'Risk & control',
                'action' => 'Wallet freeze under AML case',
                'description' => 'AML can freeze immediately for rapid response. Compliance is auto-notified; no pre-approval required but action is fully audited.',
                'resource' => 'aml_cases',
                'threshold' => null,
                'enforced' => true,
                'enforcement' => 'live',
                'sort_order' => 80,
                'role_pairs' => [
                    [
                        'id' => 'mc-wallet-freeze-p1',
                        'label' => 'Emergency freeze',
                        'maker_roles' => ['aml_analyst'],
                        'checker_roles' => ['compliance_officer'],
                    ],
                ],
            ],
        ];

        foreach ($policies as $policy) {
            MakerCheckerPolicy::query()->updateOrCreate(
                ['id' => $policy['id']],
                $policy,
            );
        }
    }
}
