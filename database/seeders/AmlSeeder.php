<?php

namespace Database\Seeders;

use App\Enums\AmlAlertSeverity;
use App\Enums\AmlAlertStatus;
use App\Enums\AmlCaseStatus;
use App\Enums\AmlSubjectType;
use App\Enums\SanctionHitStatus;
use App\Enums\StrFilingStatus;
use App\Models\Agent;
use App\Models\AmlAlert;
use App\Models\AmlCase;
use App\Models\AmlCaseEvent;
use App\Models\SanctionsHit;
use App\Models\StrFiling;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AmlSeeder extends Seeder
{
    public function run(): void
    {
        $amlAnalyst = User::query()->where('email', 'aml.analyst@iwallet.demo')->first();
        $compliance = User::query()->where('email', 'compliance@iwallet.demo')->first();
        $agent = Agent::query()->where('code', 'AGT-001')->first();
        $wallet = Wallet::query()->where('account_number', '3712345001')->first();

        if (! $amlAnalyst) {
            return;
        }

        $alerts = $this->seedAlerts($amlAnalyst, $agent);
        $cases = $this->seedCases($alerts, $amlAnalyst, $agent, $wallet);
        $this->seedSanctions($amlAnalyst, $compliance, $wallet);
        $this->seedStrFilings($cases, $amlAnalyst, $compliance);
    }

    /**
     * @return array<string, AmlAlert>
     */
    private function seedAlerts(User $analyst, ?Agent $agent): array
    {
        $rows = [
            [
                'reference' => 'ALT-2026-8821',
                'severity' => AmlAlertSeverity::High,
                'title' => 'Agent AGT-08291 · 47 cash-ins in 2 hrs',
                'narrative' => 'Agent AGT-08291 processed 47 cash-in transactions totaling ₦9.85M within a 2-hour window — pattern consistent with structuring.',
                'typology' => 'Structuring',
                'score' => 94,
                'subject_type' => AmlSubjectType::Agent,
                'subject_id' => $agent?->code ?? 'AGT-08291',
                'status' => AmlAlertStatus::Assigned,
                'metadata' => ['sub' => 'Total ₦9.85M · 14 unique customers · Onitsha'],
                'created_at' => now()->subHours(2)->subMinutes(14),
            ],
            [
                'reference' => 'ALT-2026-8814',
                'severity' => AmlAlertSeverity::Medium,
                'title' => 'Wallet W-44102 · PEP match',
                'narrative' => 'Name match 92% against domestic PEP list. Customer tier 2 — enhanced due diligence required.',
                'typology' => 'PEP screening',
                'score' => 87,
                'subject_type' => AmlSubjectType::Wallet,
                'subject_id' => 'W-44102',
                'status' => AmlAlertStatus::Open,
                'metadata' => ['sub' => 'Politically exposed person — Lagos'],
                'created_at' => now()->subHours(4)->subMinutes(2),
            ],
            [
                'reference' => 'ALT-2026-8802',
                'severity' => AmlAlertSeverity::Low,
                'title' => 'Customer C-99201 · Dormant reactivation',
                'narrative' => 'Account dormant 94 days; sudden inflow exceeds 30-day average by 12×.',
                'typology' => 'Dormant',
                'score' => 71,
                'subject_type' => AmlSubjectType::Customer,
                'subject_id' => 'C-99201',
                'status' => AmlAlertStatus::Closed,
                'metadata' => ['sub' => '₦2.1M in 24h after 90d idle'],
                'created_at' => now()->subHours(6)->subMinutes(48),
            ],
            [
                'reference' => 'ALT-2026-8798',
                'severity' => AmlAlertSeverity::High,
                'title' => 'Agent AGT-04481 · Velocity breach',
                'narrative' => 'Outbound transfer velocity exceeded tier-3 daily limit within single session.',
                'typology' => 'Velocity',
                'score' => 91,
                'subject_type' => AmlSubjectType::Agent,
                'subject_id' => 'AGT-04481',
                'status' => AmlAlertStatus::Escalated,
                'metadata' => ['sub' => '₦18.2M outbound in 45 min · Enugu'],
                'created_at' => now()->subHours(8)->subMinutes(10),
            ],
            [
                'reference' => 'ALT-2026-8781',
                'severity' => AmlAlertSeverity::Medium,
                'title' => 'Wallet W-22910 · Round amounts',
                'narrative' => 'Repeated round-number deposits from unrelated funding sources in 6-hour window.',
                'typology' => 'Structuring',
                'score' => 82,
                'subject_type' => AmlSubjectType::Wallet,
                'subject_id' => 'W-22910',
                'status' => AmlAlertStatus::Open,
                'metadata' => ['sub' => '12 × ₦500,000 deposits · PH'],
                'created_at' => now()->subHours(11)->subMinutes(22),
            ],
        ];

        $seeded = [];
        foreach ($rows as $row) {
            $createdAt = $row['created_at'];
            unset($row['created_at']);

            $alert = AmlAlert::query()->updateOrCreate(
                ['reference' => $row['reference']],
                array_merge($row, [
                    'assignee_id' => in_array($row['status'], [AmlAlertStatus::Assigned, AmlAlertStatus::Escalated], true)
                        ? $analyst->id
                        : null,
                ]),
            );

            $alert->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $seeded[$alert->reference] = $alert;
        }

        return $seeded;
    }

    /**
     * @param  array<string, AmlAlert>  $alerts
     * @return array<string, AmlCase>
     */
    private function seedCases(array $alerts, User $analyst, ?Agent $agent, ?Wallet $wallet): array
    {
        $rows = [
            [
                'reference' => 'CASE-2026-1188',
                'alert_ref' => 'ALT-2026-8821',
                'title' => 'AGT-08291 structuring cluster',
                'summary' => 'Multi-transaction structuring pattern linked to Onitsha agent hub.',
                'status' => AmlCaseStatus::UnderReview,
                'priority' => 'High',
                'typology' => 'Structuring',
                'subject_label' => ($agent ? "{$agent->business_name} · {$agent->code}" : 'Mama Ngozi Provisions · AGT-08291'),
                'subject_type' => AmlSubjectType::Agent,
                'subject_id' => $agent?->code ?? 'AGT-08291',
                'opened_at' => now()->startOfDay()->addHours(7)->addMinutes(40),
            ],
            [
                'reference' => 'CASE-2026-1182',
                'alert_ref' => 'ALT-2026-8814',
                'title' => 'W-44102 PEP escalation',
                'summary' => 'PEP name match requiring enhanced due diligence.',
                'status' => AmlCaseStatus::Open,
                'priority' => 'Medium',
                'typology' => 'PEP screening',
                'subject_label' => 'Wallet W-44102',
                'subject_type' => AmlSubjectType::Wallet,
                'subject_id' => 'W-44102',
                'opened_at' => now()->subDay()->setTime(16, 12),
            ],
            [
                'reference' => 'CASE-2026-1170',
                'alert_ref' => 'ALT-2026-8798',
                'title' => 'Port Harcourt velocity ring',
                'summary' => 'Coordinated velocity breach across Rivers hub agents.',
                'status' => AmlCaseStatus::Escalated,
                'priority' => 'High',
                'typology' => 'Velocity',
                'subject_label' => 'Multi-agent · Rivers hub',
                'subject_type' => AmlSubjectType::Agent,
                'subject_id' => 'AGT-04481',
                'opened_at' => Carbon::parse('2026-05-19 09:00:00'),
            ],
            [
                'reference' => 'CASE-2026-1155',
                'alert_ref' => 'ALT-2026-8802',
                'title' => 'C-99201 dormant reactivation',
                'summary' => 'Dormant account reactivation with abnormal inflow.',
                'status' => AmlCaseStatus::Closed,
                'priority' => 'Low',
                'typology' => 'Dormant',
                'subject_label' => 'Customer C-99201',
                'subject_type' => AmlSubjectType::Customer,
                'subject_id' => 'C-99201',
                'opened_at' => Carbon::parse('2026-05-18 11:30:00'),
                'closed_at' => Carbon::parse('2026-05-22 16:00:00'),
            ],
        ];

        $seeded = [];
        foreach ($rows as $row) {
            $alertRef = $row['alert_ref'];
            unset($row['alert_ref']);

            $metadata = [
                'priority' => $row['priority'],
                'typology' => $row['typology'],
                'subject_label' => $row['subject_label'],
            ];
            unset($row['priority'], $row['typology'], $row['subject_label']);

            $case = AmlCase::query()->updateOrCreate(
                ['reference' => $row['reference']],
                array_merge($row, [
                    'alert_id' => $alerts[$alertRef]->id ?? null,
                    'assignee_id' => $analyst->id,
                    'metadata' => $metadata,
                ]),
            );

            AmlCaseEvent::query()->firstOrCreate(
                [
                    'case_id' => $case->id,
                    'action' => 'case_opened',
                ],
                [
                    'actor_id' => $analyst->id,
                    'notes' => 'Case opened from monitoring queue',
                    'created_at' => $row['opened_at'],
                ],
            );

            if ($case->status === AmlCaseStatus::Closed) {
                AmlCaseEvent::query()->firstOrCreate(
                    [
                        'case_id' => $case->id,
                        'action' => 'closed',
                    ],
                    [
                        'actor_id' => $analyst->id,
                        'notes' => 'Case closed — no further action required',
                        'created_at' => $row['closed_at'],
                    ],
                );
            }

            $seeded[$case->reference] = $case;
        }

        return $seeded;
    }

    private function seedSanctions(User $analyst, ?User $compliance, ?Wallet $wallet): void
    {
        $rows = [
            [
                'reference' => 'SAN-44021',
                'list_name' => 'UN consolidated',
                'matched_name' => 'OKAFOR, Chinedu',
                'subject_type' => 'customer',
                'subject_id' => 'C-44102',
                'match_score' => 88,
                'status' => SanctionHitStatus::PendingReview,
                'created_at' => now()->startOfDay()->addHours(8)->addMinutes(12),
            ],
            [
                'reference' => 'SAN-44018',
                'list_name' => 'Domestic PEP',
                'matched_name' => 'ADELEKE, Funmi',
                'subject_type' => 'wallet',
                'subject_id' => $wallet ? "W-{$wallet->id}" : 'W-44102',
                'match_score' => 92,
                'status' => SanctionHitStatus::PendingReview,
                'created_at' => now()->startOfDay()->addHours(7)->addMinutes(55),
            ],
            [
                'reference' => 'SAN-43990',
                'list_name' => 'OFAC SDN',
                'matched_name' => 'GLOBAL TRADE LLC',
                'subject_type' => 'merchant',
                'subject_id' => 'M-8821',
                'match_score' => 76,
                'status' => SanctionHitStatus::FalsePositive,
                'reviewed_by_id' => $analyst->id,
                'created_at' => now()->subDay()->setTime(14, 20),
            ],
            [
                'reference' => 'SAN-43972',
                'list_name' => 'EU consolidated',
                'matched_name' => 'NWOSU, Emeka P.',
                'subject_type' => 'agent',
                'subject_id' => 'AGT-12044',
                'match_score' => 81,
                'status' => SanctionHitStatus::ConfirmedMatch,
                'reviewed_by_id' => $compliance?->id ?? $analyst->id,
                'created_at' => Carbon::parse('2026-05-18 10:00:00'),
            ],
        ];

        foreach ($rows as $row) {
            $createdAt = $row['created_at'];
            unset($row['created_at']);

            $hit = SanctionsHit::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );

            $hit->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }
    }

    /**
     * @param  array<string, AmlCase>  $cases
     */
    private function seedStrFilings(array $cases, User $analyst, ?User $compliance): void
    {
        $rows = [
            [
                'reference' => 'STR-2026-0041',
                'case_ref' => 'CASE-2026-1170',
                'title' => 'Port Harcourt velocity ring',
                'narrative' => 'Suspicious velocity pattern across Rivers hub agents.',
                'amount_ngn' => 42_000_000,
                'status' => StrFilingStatus::Acknowledged,
                'maker_id' => $analyst->id,
                'checker_id' => $compliance?->id,
                'submitted_at' => Carbon::parse('2026-05-20 15:30:00'),
                'nfiu_reference' => 'NFIU-ACK-88210',
            ],
            [
                'reference' => 'STR-2026-0042',
                'case_ref' => 'CASE-2026-1188',
                'title' => 'AGT-08291 structuring cluster',
                'narrative' => 'Structuring pattern via agent cash-ins.',
                'amount_ngn' => 9_850_000,
                'status' => StrFilingStatus::Submitted,
                'maker_id' => $analyst->id,
                'checker_id' => $compliance?->id,
                'submitted_at' => now()->startOfDay()->addHours(9),
            ],
            [
                'reference' => 'STR-2026-0040',
                'case_ref' => 'CASE-2026-1155',
                'title' => 'C-99201 dormant reactivation',
                'narrative' => 'Dormant account sudden reactivation with large inflow.',
                'amount_ngn' => 2_100_000,
                'status' => StrFilingStatus::Acknowledged,
                'maker_id' => $analyst->id,
                'checker_id' => $compliance?->id,
                'submitted_at' => Carbon::parse('2026-05-18 14:00:00'),
                'nfiu_reference' => 'NFIU-ACK-88102',
            ],
            [
                'reference' => 'STR-2026-0038',
                'case_ref' => 'CASE-2026-1182',
                'title' => 'W-44102 PEP escalation',
                'narrative' => 'PEP match requiring STR filing pending final review.',
                'amount_ngn' => 0,
                'status' => StrFilingStatus::Draft,
                'maker_id' => $analyst->id,
            ],
        ];

        foreach ($rows as $row) {
            $caseRef = $row['case_ref'];
            unset($row['case_ref']);

            StrFiling::query()->updateOrCreate(
                ['reference' => $row['reference']],
                array_merge($row, [
                    'case_id' => $cases[$caseRef]->id ?? null,
                ]),
            );
        }
    }
}
