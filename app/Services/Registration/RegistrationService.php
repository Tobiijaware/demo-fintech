<?php

namespace App\Services\Registration;

use App\Enums\KycLevel;
use App\Enums\KycStatus;
use App\Enums\OnboardingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\RegistrationException;
use App\Models\User;
use App\Models\KycVerification;
use App\Enums\UserType;
use App\Services\Kyc\KycService;
use App\Services\Kyc\TierCriteriaService;
use App\Services\Onboarding\OnboardingApplicationService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RegistrationService
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private WalletService $walletService,
        private KycService $kycService,
        private OnboardingApplicationService $onboardingService,
        private RegistrationProfileService $registrationProfileService,
    ) {}

    public function sendVerificationCode(string $email): void
    {
        $this->emailVerificationService->sendCode($email);
    }

    public function resendVerificationCode(string $email): void
    {
        $this->emailVerificationService->sendCode($email);
    }

    public function verifyEmail(string $email, string $code): array
    {
        $this->emailVerificationService->verifyCode($email, $code);

        return [
            'email' => $email,
            'verified' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function signupCriteria(): array
    {
        return app(TierCriteriaService::class)->signupCriteria('customer');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveRegistrationProfile(array $data): array
    {
        return $this->registrationProfileService->saveProfile(
            $data['email'],
            $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function validateRegistrationBvn(string $email, string $bvn): array
    {
        return $this->registrationProfileService->validateBvn($email, $bvn);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function completeRegistration(array $data): array
    {
        $email = $data['email'];

        if (User::query()->where('email', $email)->exists()) {
            throw new RegistrationException('An account with this email already exists.', 409);
        }

        $this->emailVerificationService->assertEmailVerifiedForRegistration($email);
        $profile = $this->registrationProfileService->assertReadyForCompletion($email);

        $user = DB::transaction(function () use ($data, $email, $profile) {
            $user = User::query()->create([
                'email' => $email,
                'password' => $data['password'],
                'firstname' => $profile['firstname'],
                'lastname' => $profile['lastname'],
                'phone' => $profile['phone'],
                'dob' => $profile['date_of_birth'],
                'bvn' => $profile['bvn'],
                'user_type' => UserType::Customer,
                'role' => UserRole::Customer,
                'status' => UserStatus::Pending,
                'email_verified_at' => now(),
            ]);

            $wallet = $this->walletService->createNgnWallet($user);
            $kyc = $this->kycService->initializeForUser($user);
            KycVerification::query()
                ->where('user_id', $user->id)
                ->where('level', KycLevel::IdentityVerification)
                ->update([
                    'status' => KycStatus::Submitted,
                    'submitted_at' => now(),
                    'payload' => [
                        'bvn' => $profile['bvn'],
                        'provider' => 'swwipe',
                        'verified_at' => now()->toIso8601String(),
                        'entity' => $profile['bvn_entity'] ?? [],
                        'resolved_name' => $profile['resolved_name'] ?? null,
                    ],
                ]);
            $onboarding = $this->onboardingService->createFromMobileCustomer($user, [
                'status' => OnboardingStatus::Draft,
                'submitted_at' => null,
            ]);

            $this->registrationProfileService->forget($email);

            return compact('user', 'wallet', 'kyc', 'onboarding');
        });

        $token = JWTAuth::fromUser($user['user']);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $user['user'],
            'wallet' => $user['wallet'],
            'kyc' => $user['kyc'],
        ];
    }
}
