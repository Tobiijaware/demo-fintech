<?php

namespace Database\Seeders;

use App\Enums\FilingStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\PolicyCategory;
use App\Enums\PolicyStatus;
use App\Models\ComplianceAuditFinding;
use App\Models\CompliancePolicy;
use App\Models\Regulator;
use App\Models\RegulatoryFiling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ComplianceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRegulators();
        $this->seedFilings();
        $this->seedAuditFindings();
        $this->seedPolicies();
    }

    private function seedRegulators(): void
    {
        $rows = [
            [
                'code' => 'CBN',
                'name' => 'Central Bank of Nigeria',
                'status' => 'Compliant',
                'last_submission' => '2026-04-30',
                'next_due' => '2026-05-22',
                'contact_email' => 'psp.reporting@cbn.gov.ng',
                'filings_ytd' => 14,
            ],
            [
                'code' => 'NDIC',
                'name' => 'Nigeria Deposit Insurance Corporation',
                'status' => 'Compliant',
                'last_submission' => '2026-04-28',
                'next_due' => '2026-05-30',
                'contact_email' => 'returns@ndic.gov.ng',
                'filings_ytd' => 4,
            ],
            [
                'code' => 'NFIU',
                'name' => 'Nigerian Financial Intelligence Unit',
                'status' => 'Report due 18 May',
                'last_submission' => '2026-04-15',
                'next_due' => '2026-05-18',
                'contact_email' => 'reporting@nfiu.gov.ng',
                'filings_ytd' => 9,
            ],
            [
                'code' => 'NIBSS',
                'name' => 'NIBSS PLC',
                'status' => 'Compliant',
                'last_submission' => '2026-05-10',
                'next_due' => '2026-06-10',
                'contact_email' => 'settlement@nibss-plc.com.ng',
                'filings_ytd' => 5,
            ],
            [
                'code' => 'SEC',
                'name' => 'Securities & Exchange Commission',
                'status' => 'Compliant',
                'last_submission' => '2026-03-01',
                'next_due' => '2026-09-01',
                'contact_email' => 'disclosure@sec.gov.ng',
                'filings_ytd' => 2,
            ],
        ];

        foreach ($rows as $row) {
            Regulator::query()->updateOrCreate(['code' => $row['code']], $row);
        }
    }

    private function seedFilings(): void
    {
        $filings = [
            [
                'reference' => 'FIL-2026-041',
                'title' => 'Monthly STR aggregate report',
                'description' => '9 STRs · 4 sanction hits',
                'regulator' => 'NFIU',
                'due_date' => '2026-05-18',
                'status' => FilingStatus::InReview,
                'owner_name' => 'AML desk',
                'frequency' => 'Monthly',
            ],
            [
                'reference' => 'FIL-2026-038',
                'title' => 'Quarterly capital return',
                'description' => 'Q1 2026',
                'regulator' => 'CBN',
                'due_date' => '2026-05-22',
                'status' => FilingStatus::Pending,
                'owner_name' => 'Finance',
                'frequency' => 'Quarterly',
            ],
            [
                'reference' => 'FIL-2026-035',
                'title' => 'Agent network statistical return',
                'description' => 'PSSP agent roll-up',
                'regulator' => 'CBN',
                'due_date' => '2026-05-25',
                'status' => FilingStatus::Draft,
                'owner_name' => 'Compliance',
                'frequency' => 'Monthly',
            ],
            [
                'reference' => 'FIL-2026-030',
                'title' => 'NDIC premium assessment',
                'description' => 'Deposit insurance',
                'regulator' => 'NDIC',
                'due_date' => '2026-05-30',
                'status' => FilingStatus::Pending,
                'owner_name' => 'Finance',
                'frequency' => 'Quarterly',
            ],
            [
                'reference' => 'FIL-2026-028',
                'title' => 'NIBSS settlement reconciliation',
                'description' => 'April cycle',
                'regulator' => 'NIBSS',
                'due_date' => '2026-05-12',
                'status' => FilingStatus::Submitted,
                'owner_name' => 'Settlement',
                'frequency' => 'Monthly',
                'submitted_at' => Carbon::parse('2026-05-12 14:00:00'),
            ],
            [
                'reference' => 'FIL-2026-022',
                'title' => 'KYC exception register',
                'description' => 'Tier upgrades & overrides',
                'regulator' => 'CBN',
                'due_date' => '2026-05-15',
                'status' => FilingStatus::InReview,
                'owner_name' => 'KYC',
                'frequency' => 'Monthly',
            ],
        ];

        foreach ($filings as $row) {
            RegulatoryFiling::query()->updateOrCreate(['reference' => $row['reference']], $row);
        }
    }

    private function seedAuditFindings(): void
    {
        $findings = [
            [
                'reference' => 'AF-2026-014',
                'area' => 'KYC',
                'title' => 'Maker-checker bypass on 2 mobile approvals',
                'severity' => FindingSeverity::Critical,
                'status' => FindingStatus::Open,
                'owner' => 'Compliance',
                'due_date' => '2026-05-20',
                'opened_at' => '2026-05-10',
            ],
            [
                'reference' => 'AF-2026-011',
                'area' => 'Access control',
                'title' => 'Dormant admin accounts with write access',
                'severity' => FindingSeverity::High,
                'status' => FindingStatus::InProgress,
                'owner' => 'IT Security',
                'due_date' => '2026-05-25',
                'opened_at' => '2026-05-02',
            ],
            [
                'reference' => 'AF-2026-009',
                'area' => 'AML',
                'title' => 'STR filing SLA not met for 1 alert',
                'severity' => FindingSeverity::High,
                'status' => FindingStatus::Open,
                'owner' => 'AML',
                'due_date' => '2026-05-19',
                'opened_at' => '2026-05-08',
            ],
            [
                'reference' => 'AF-2026-006',
                'area' => 'Data retention',
                'title' => 'KYC document retention policy gap',
                'severity' => FindingSeverity::Medium,
                'status' => FindingStatus::InProgress,
                'owner' => 'Compliance',
                'due_date' => '2026-06-30',
                'opened_at' => '2026-04-15',
            ],
            [
                'reference' => 'AF-2026-003',
                'area' => 'Operations',
                'title' => 'Settlement cut-off documentation outdated',
                'severity' => FindingSeverity::Low,
                'status' => FindingStatus::Remediated,
                'owner' => 'Settlement',
                'due_date' => '2026-05-01',
                'opened_at' => '2026-03-01',
                'remediation_notes' => 'Updated settlement SOP and retrained ops team.',
            ],
        ];

        foreach ($findings as $row) {
            ComplianceAuditFinding::query()->updateOrCreate(['reference' => $row['reference']], $row);
        }
    }

    private function seedPolicies(): void
    {
        $policies = [
            ['reference' => 'POL-001', 'name' => 'KYC & customer due diligence', 'version' => '4.2', 'owner' => 'Compliance', 'effective_date' => '2026-01-01', 'review_due' => '2027-01-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Onboarding, 'summary' => 'Tier limits, identity checks, and periodic refresh rules'],
            ['reference' => 'POL-007', 'name' => 'Beneficial ownership & PEP screening', 'version' => '2.1', 'owner' => 'Compliance', 'effective_date' => '2025-10-10', 'review_due' => '2026-10-10', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Onboarding, 'summary' => 'UBO thresholds and enhanced due diligence triggers'],
            ['reference' => 'POL-008', 'name' => 'Document retention & evidence standards', 'version' => '1.6', 'owner' => 'Compliance', 'effective_date' => '2025-04-01', 'review_due' => '2026-06-15', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Onboarding, 'summary' => 'Capture quality, storage duration, and audit trail requirements'],
            ['reference' => 'POL-009', 'name' => 'Minor & vulnerable customer onboarding', 'version' => '1.0', 'owner' => 'Compliance', 'effective_date' => '2026-02-01', 'review_due' => '2027-02-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Onboarding, 'summary' => 'Guardian consent and reduced-limit account handling'],
            ['reference' => 'POL-002', 'name' => 'Agent onboarding & PSSP standards', 'version' => '3.1', 'owner' => 'Compliance', 'effective_date' => '2025-11-15', 'review_due' => '2026-11-15', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Agents, 'summary' => 'Field verification, terminal allocation, and go-live checklist'],
            ['reference' => 'POL-010', 'name' => 'Agent commission & float management', 'version' => '2.4', 'owner' => 'Operations', 'effective_date' => '2026-01-01', 'review_due' => '2026-07-01', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Agents, 'summary' => 'Float caps, settlement windows, and commission clawback'],
            ['reference' => 'POL-011', 'name' => 'Agent termination & offboarding', 'version' => '1.2', 'owner' => 'Compliance', 'effective_date' => '2025-09-01', 'review_due' => '2026-09-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Agents, 'summary' => 'Terminal recall, final settlement, and data purge timelines'],
            ['reference' => 'POL-003', 'name' => 'AML transaction monitoring', 'version' => '5.0', 'owner' => 'AML', 'effective_date' => '2026-03-01', 'review_due' => '2026-09-01', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Aml, 'summary' => 'Scenario thresholds, alert triage SLAs, and escalation paths'],
            ['reference' => 'POL-012', 'name' => 'Sanctions & PEP list management', 'version' => '3.3', 'owner' => 'AML', 'effective_date' => '2025-12-01', 'review_due' => '2026-05-28', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Aml, 'summary' => 'List refresh cadence, false-positive handling, and blocking rules'],
            ['reference' => 'POL-013', 'name' => 'Suspicious transaction reporting (STR)', 'version' => '4.1', 'owner' => 'AML', 'effective_date' => '2025-06-01', 'review_due' => '2026-06-01', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Aml, 'summary' => 'NFIU filing timelines, case documentation, and quality review'],
            ['reference' => 'POL-014', 'name' => 'Cash-intensive agent corridor controls', 'version' => '2.0', 'owner' => 'AML', 'effective_date' => '2026-02-01', 'review_due' => '2027-02-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Aml, 'summary' => 'Geographic limits, velocity caps, and enhanced monitoring'],
            ['reference' => 'POL-004', 'name' => 'Information security & access control', 'version' => '2.8', 'owner' => 'IT Security', 'effective_date' => '2025-06-01', 'review_due' => '2026-06-01', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Security, 'summary' => 'MFA, privileged access, and back-office role segregation'],
            ['reference' => 'POL-015', 'name' => 'Data privacy & NDPR compliance', 'version' => '1.5', 'owner' => 'Legal', 'effective_date' => '2026-01-01', 'review_due' => '2026-05-20', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Security, 'summary' => 'Consent, data subject requests, and breach notification'],
            ['reference' => 'POL-016', 'name' => 'Incident response & business continuity', 'version' => '2.2', 'owner' => 'IT Security', 'effective_date' => '2025-03-01', 'review_due' => '2026-03-01', 'status' => PolicyStatus::Archived, 'category' => PolicyCategory::Security, 'summary' => 'Severity classes, war-room protocol, and recovery RTOs'],
            ['reference' => 'POL-017', 'name' => 'Vendor & third-party risk', 'version' => '1.1', 'owner' => 'IT Security', 'effective_date' => '2026-01-15', 'review_due' => '2027-01-15', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Security, 'summary' => 'Due diligence, contract clauses, and ongoing monitoring'],
            ['reference' => 'POL-005', 'name' => 'Regulatory reporting calendar', 'version' => '1.4', 'owner' => 'Compliance', 'effective_date' => '2026-02-01', 'review_due' => '2027-02-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Reporting, 'summary' => 'CBN, NFIU, and NDIC filing ownership and deadlines'],
            ['reference' => 'POL-018', 'name' => 'Management information & board packs', 'version' => '2.0', 'owner' => 'Finance', 'effective_date' => '2026-04-01', 'review_due' => '2027-04-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Reporting, 'summary' => 'Monthly KPI definitions and sign-off workflow'],
            ['reference' => 'POL-019', 'name' => 'Capital adequacy & liquidity reporting', 'version' => '3.0', 'owner' => 'Treasury', 'effective_date' => '2025-10-01', 'review_due' => '2026-06-30', 'status' => PolicyStatus::ReviewDue, 'category' => PolicyCategory::Reporting, 'summary' => 'Internal limits aligned to CBN prudential returns'],
            ['reference' => 'POL-006', 'name' => 'Customer complaints & disputes', 'version' => '2.0', 'owner' => 'Support', 'effective_date' => '2024-08-01', 'review_due' => '2025-08-01', 'status' => PolicyStatus::Archived, 'category' => PolicyCategory::Support, 'summary' => 'SLA tiers, escalation to compliance, and compensation caps'],
            ['reference' => 'POL-020', 'name' => 'Transaction reversal & goodwill credits', 'version' => '1.8', 'owner' => 'Support', 'effective_date' => '2025-11-01', 'review_due' => '2026-11-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Support, 'summary' => 'Approval matrix, evidence requirements, and fraud carve-outs'],
            ['reference' => 'POL-021', 'name' => 'Vulnerable customer handling', 'version' => '1.0', 'owner' => 'Support', 'effective_date' => '2026-05-01', 'review_due' => '2027-05-01', 'status' => PolicyStatus::Current, 'category' => PolicyCategory::Support, 'summary' => 'Accessibility, hardship flags, and restricted outreach'],
        ];

        foreach ($policies as $row) {
            CompliancePolicy::query()->updateOrCreate(['reference' => $row['reference']], $row);
        }
    }
}
