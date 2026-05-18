<?php

namespace App\Services\Registration;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\RegistrationException;
use App\Models\User;
use App\Services\Kyc\KycService;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RegistrationService
{
    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private WalletService $walletService,
        private KycService $kycService,
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
     * @param  array<string, mixed>  $data
     */
    public function completeRegistration(array $data): array
    {
        $email = $data['email'];

        if (User::query()->where('email', $email)->exists()) {
            throw new RegistrationException('An account with this email already exists.', 409);
        }

        $this->emailVerificationService->assertEmailVerifiedForRegistration($email);

        $user = DB::transaction(function () use ($data, $email) {
            $user = User::query()->create([
                'email' => $email,
                'password' => $data['password'],
                'role' => UserRole::Customer,
                'status' => UserStatus::Pending,
                'email_verified_at' => now(),
            ]);

            $wallet = $this->walletService->createNgnWallet($user);
            $kyc = $this->kycService->initializeForUser($user);

            return compact('user', 'wallet', 'kyc');
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
