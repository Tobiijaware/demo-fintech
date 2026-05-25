<?php

namespace Database\Seeders;

use App\Enums\ProvisioningRequestStatus;
use App\Enums\ProvisioningRequestType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\BackofficeRole;
use App\Models\ProvisioningRequest;
use App\Models\User;
use App\Services\Governance\ProvisioningRequestService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProvisioningRequestSeeder extends Seeder
{
    public function run(): void
    {
        $kycRole = BackofficeRole::query()->where('slug', 'kyc_officer')->first();
        if (! $kycRole) {
            return;
        }

        $requester = User::query()->updateOrCreate(
            ['email' => 'akin.olukoya@iwallet.demo'],
            [
                'firstname' => 'Akin',
                'lastname' => 'Olukoya',
                'password' => Hash::make('Password123!'),
                'user_type' => UserType::Staff,
                'backoffice_role_id' => $kycRole->id,
                'role' => 'admin',
                'status' => UserStatus::Approved,
                'job_title' => 'Head of Onboarding',
                'hub' => 'Lagos',
                'email_verified_at' => now(),
            ],
        );

        ProvisioningRequest::query()->updateOrCreate(
            ['reference' => 'PROV-08412'],
            [
                'type' => ProvisioningRequestType::NewUser,
                'status' => ProvisioningRequestStatus::Pending,
                'requested_by_id' => $requester->id,
                'subject' => [
                    'title' => 'New user — Chioma Okwu',
                    'sub' => 'Replacing Adaeze — maternity leave coverage',
                    'role' => 'KYC officer',
                    'role_slug' => 'kyc_officer',
                    'region' => 'Lagos',
                    'request_note' => 'Maternity coverage for Adaeze Okonkwo',
                    'duration' => '14 May – 14 Nov 2026',
                    'reports_to' => 'Akin Olukoya',
                    'user_details' => [
                        'name' => 'Chioma Adaobi Okwu',
                        'email' => 'chioma.okwu@r4gtech.ng',
                        'staff_id' => 'STF-04812',
                        'hire_date' => '12 May 2026',
                        'training' => '✓ Cleared',
                        'background_check' => '✓ AML + data protection',
                    ],
                    'permissions' => [
                        'Review applications',
                        'Verify BVN/NIN/CAC',
                        'Approve Tier 1–2',
                        'Query / Reject',
                    ],
                    'permission_note' => 'Tier 3 approval requires additional senior KYC officer permission — not granted at this level.',
                    'mfa_required' => true,
                    'password_expiry' => true,
                ],
            ],
        );

        $settlementLead = User::query()->where('email', 'settlement@iwallet.demo')->first();
        if ($settlementLead) {
            ProvisioningRequest::query()->updateOrCreate(
                ['reference' => 'PROV-08408'],
                [
                    'type' => ProvisioningRequestType::RoleChange,
                    'status' => ProvisioningRequestStatus::Pending,
                    'requested_by_id' => $settlementLead->id,
                    'subject' => [
                        'title' => 'Role change — Tunde Bakare',
                        'sub' => 'Promotion to Settlement Sr',
                        'role' => 'Settlement Sr',
                        'role_slug' => 'settlement_officer',
                        'region' => 'Lagos hub',
                        'request_note' => 'Internal promotion — requires elevated settlement write access',
                        'duration' => 'Effective 20 May 2026',
                        'reports_to' => 'Ngozi Okeke',
                        'user_details' => [
                            'name' => 'Tunde Bakare',
                            'email' => 'tunde.bakare@iwallet.demo',
                            'staff_id' => 'STF-03102',
                            'hire_date' => 'March 2024',
                            'training' => '✓ Cleared',
                            'background_check' => '✓ Complete',
                        ],
                        'permissions' => [
                            'Settlement & recon (write)',
                            'Exception resolution',
                            'Manual wallet credit up to ₦1M',
                        ],
                        'mfa_required' => true,
                        'password_expiry' => true,
                    ],
                ],
            );
        }
    }
}
