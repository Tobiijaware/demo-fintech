<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditLog::query()->exists()) {
            return;
        }

        $actor = User::query()
            ->whereHas('backofficeRole', fn ($q) => $q->where('slug', 'super_admin'))
            ->first();

        $samples = [
            [
                'action' => 'role.updated',
                'resource_type' => 'BackofficeRole',
                'resource_id' => '2',
                'summary' => 'Updated permissions for KYC Officer role',
                'metadata' => ['before' => ['kyc_applications' => 'read'], 'after' => ['kyc_applications' => 'write']],
            ],
            [
                'action' => 'user.created',
                'resource_type' => 'User',
                'resource_id' => '10',
                'summary' => 'Created back-office user Jane Doe (jane.doe@example.com)',
                'metadata' => ['email' => 'jane.doe@example.com', 'backoffice_role_id' => 3],
            ],
            [
                'action' => 'user.updated',
                'resource_type' => 'User',
                'resource_id' => '8',
                'summary' => 'Updated back-office user role assignment',
                'metadata' => ['before' => ['backoffice_role_id' => 2], 'after' => ['backoffice_role_id' => 3]],
            ],
            [
                'action' => 'maker_checker_policy.created',
                'resource_type' => 'MakerCheckerPolicy',
                'resource_id' => 'kyc.approve',
                'summary' => 'Created maker-checker policy for KYC approval',
                'metadata' => ['department' => 'Onboarding', 'enforced' => true],
            ],
            [
                'action' => 'onboarding.approved',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '1',
                'summary' => 'Approved onboarding application KYC-2026-ABCDE',
                'metadata' => ['reference' => 'KYC-2026-ABCDE', 'tier' => 'tier2'],
            ],
            [
                'action' => 'onboarding.rejected',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '2',
                'summary' => 'Rejected onboarding application KYC-2026-FGHIJ',
                'metadata' => ['reason' => 'BVN verification failed'],
            ],
            [
                'action' => 'onboarding.queried',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '3',
                'summary' => 'Queried applicant for missing CAC document',
                'metadata' => ['notes' => 'Please upload valid CAC certificate'],
            ],
            [
                'action' => 'onboarding.submitted',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '4',
                'summary' => 'Submitted onboarding application for review',
                'metadata' => ['from_status' => 'draft', 'to_status' => 'pending_review'],
            ],
            [
                'action' => 'onboarding.created',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '5',
                'summary' => 'Created internal onboarding draft',
                'metadata' => ['channel' => 'internal', 'applicant_type' => 'agent'],
            ],
            [
                'action' => 'onboarding.hold',
                'resource_type' => 'OnboardingApplication',
                'resource_id' => '6',
                'summary' => 'Placed onboarding application on hold pending AML review',
                'metadata' => ['notes' => 'Awaiting AML clearance'],
            ],
        ];

        foreach ($samples as $index => $sample) {
            AuditLog::query()->create([
                'actor_id' => $actor?->id,
                'actor_email' => $actor?->email ?? 'admin@demo.local',
                'actor_role_slug' => $actor?->backofficeRole?->slug ?? 'super_admin',
                'action' => $sample['action'],
                'resource_type' => $sample['resource_type'],
                'resource_id' => $sample['resource_id'],
                'summary' => $sample['summary'],
                'metadata' => $sample['metadata'],
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays(6 - ($index % 7))->subHours($index),
            ]);
        }
    }
}
