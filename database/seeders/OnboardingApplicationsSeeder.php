<?php

namespace Database\Seeders;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\OnboardingChannel;
use App\Enums\OnboardingStatus;
use App\Enums\VerificationCheckStatus;
use App\Models\OnboardingApplication;
use App\Models\User;
use Illuminate\Database\Seeder;

class OnboardingApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        $compliance = User::query()->where('email', 'compliance@iwallet.demo')->first();

        $samples = [
            [
                'reference' => 'KYC-2026-08291',
                'applicant_type' => ApplicantType::Agent,
                'tier' => AccountTier::Tier2,
                'status' => OnboardingStatus::PendingReview,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::Verified,
                'business_name' => 'Chinwe Adeyemi Stores',
                'proprietor_name' => 'Chinwe Adeyemi',
                'location' => 'Ikeja',
                'cac_number' => 'CAC RC 1839472',
                'business_type' => 'Sole proprietorship',
                'bvn_masked' => '****8291',
                'nin_masked' => null,
                'estimated_settlement' => 'T+1',
                'submitted_at' => now()->subDays(2)->subHours(4),
                'sla_due_at' => now()->subHours(2),
                'linked_agents' => [
                    ['agent_id' => 'AGT-04481', 'name' => 'Mama Ngozi Provisions', 'commission' => '₦220,000', 'txns' => 184],
                ],
            ],
            [
                'reference' => 'KYC-2026-08290',
                'applicant_type' => ApplicantType::Customer,
                'tier' => AccountTier::Tier1,
                'status' => OnboardingStatus::PendingReview,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::BvnMismatch,
                'business_name' => 'Adamu Yusuf',
                'proprietor_name' => 'Adamu Yusuf',
                'location' => 'Kano',
                'bvn_masked' => '****1024',
                'submitted_at' => now()->subDay(),
                'sla_due_at' => now()->addDay(),
            ],
            [
                'reference' => 'KYC-2026-08288',
                'applicant_type' => ApplicantType::Agent,
                'tier' => AccountTier::Tier3,
                'status' => OnboardingStatus::PendingReview,
                'channel' => OnboardingChannel::Internal,
                'verification_status' => VerificationCheckStatus::Verified,
                'business_name' => 'Mama Ngozi Provisions',
                'proprietor_name' => 'Ngozi Okwuosa',
                'location' => 'Onitsha main market',
                'cac_number' => 'CAC RC 9921044',
                'business_type' => 'Sole proprietorship',
                'bvn_masked' => '****4471',
                'maker_id' => $compliance?->id,
                'submitted_at' => now()->subHours(6),
                'sla_due_at' => now()->addDays(2),
            ],
            [
                'reference' => 'KYC-2026-08201',
                'applicant_type' => ApplicantType::Customer,
                'tier' => AccountTier::Tier2,
                'status' => OnboardingStatus::Approved,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::Verified,
                'business_name' => 'Blessing Okafor',
                'proprietor_name' => 'Blessing Okafor',
                'location' => 'Lagos',
                'submitted_at' => now()->subDays(3),
                'reviewed_at' => now()->subHours(2),
            ],
            [
                'reference' => 'KYC-2026-08112',
                'applicant_type' => ApplicantType::Agent,
                'tier' => AccountTier::Tier1,
                'status' => OnboardingStatus::Rejected,
                'channel' => OnboardingChannel::Internal,
                'verification_status' => VerificationCheckStatus::DocumentPending,
                'business_name' => 'Quick Cash Hub',
                'proprietor_name' => 'Emeka Nwosu',
                'location' => 'Port Harcourt',
                'rejection_reason' => 'CAC document could not be verified.',
                'maker_id' => $compliance?->id,
                'reviewed_at' => now()->subDay(),
            ],
            [
                'reference' => 'KYC-2026-08044',
                'applicant_type' => ApplicantType::Customer,
                'tier' => AccountTier::Tier2,
                'status' => OnboardingStatus::ReVerification,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::Pending,
                'business_name' => 'Tunde Bakare',
                'proprietor_name' => 'Tunde Bakare',
                'location' => 'Abuja',
                'submitted_at' => now()->subDays(5),
                'sla_due_at' => now()->addHours(12),
            ],
            [
                'reference' => 'KYC-2026-07990',
                'applicant_type' => ApplicantType::Customer,
                'tier' => AccountTier::Tier3,
                'status' => OnboardingStatus::Approved,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::Verified,
                'business_name' => 'Amina Hassan',
                'proprietor_name' => 'Amina Hassan',
                'location' => 'Kaduna',
                'reviewed_at' => now()->subDays(1),
            ],
            [
                'reference' => 'KYC-2026-07955',
                'applicant_type' => ApplicantType::Agent,
                'tier' => AccountTier::Tier2,
                'status' => OnboardingStatus::Approved,
                'channel' => OnboardingChannel::Internal,
                'verification_status' => VerificationCheckStatus::Verified,
                'business_name' => 'Swift Pay Agency',
                'proprietor_name' => 'Ibrahim Musa',
                'location' => 'Lagos',
                'maker_id' => $compliance?->id,
                'reviewed_at' => now()->subHours(8),
            ],
            [
                'reference' => 'KYC-2026-07912',
                'applicant_type' => ApplicantType::Customer,
                'tier' => AccountTier::Tier1,
                'status' => OnboardingStatus::Rejected,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::NinMismatch,
                'business_name' => 'Grace Etim',
                'proprietor_name' => 'Grace Etim',
                'location' => 'Calabar',
                'rejection_reason' => 'NIN record did not match submitted name.',
                'reviewed_at' => now()->subDays(2),
            ],
            [
                'reference' => 'KYC-2026-07880',
                'applicant_type' => ApplicantType::Agent,
                'tier' => AccountTier::Tier2,
                'status' => OnboardingStatus::ReVerification,
                'channel' => OnboardingChannel::Mobile,
                'verification_status' => VerificationCheckStatus::DocumentPending,
                'business_name' => 'Delta Cash Point',
                'proprietor_name' => 'Paul Edet',
                'location' => 'Warri',
                'submitted_at' => now()->subDays(10),
                'sla_due_at' => now()->subDay(),
            ],
        ];

        foreach ($samples as $row) {
            OnboardingApplication::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );
        }
    }
}
