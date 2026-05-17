<?php

namespace App\Services\Registration;

use App\Exceptions\RegistrationException;
use App\Mail\EmailVerificationCodeMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EmailVerificationService
{
    public function sendCode(string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            throw new RegistrationException('An account with this email already exists.', 409);
        }

        $this->assertResendAllowed($email);

        EmailVerification::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->delete();

        $plainCode = \generate_otp(6);
        $expiryMinutes = config('registration.otp_expiry_minutes');

        EmailVerification::query()->create([
            'email' => $email,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        Mail::to($email)->send(new EmailVerificationCodeMail($plainCode, $expiryMinutes));
    }

    public function verifyCode(string $email, string $code): EmailVerification
    {
        $verification = EmailVerification::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $verification) {
            throw new RegistrationException('No active verification found for this email. Request a new code.', 404);
        }

        if ($verification->isExpired()) {
            throw new RegistrationException('Verification code has expired. Request a new code.', 410);
        }

        $maxAttempts = config('registration.max_verification_attempts');

        if ($verification->attempts >= $maxAttempts) {
            throw new RegistrationException('Too many failed attempts. Request a new code.', 429);
        }

        if (! Hash::check($code, $verification->code)) {
            $verification->increment('attempts');

            throw new RegistrationException('Invalid verification code.', 422);
        }

        $verification->update(['verified_at' => now()]);

        return $verification->refresh();
    }

    public function assertEmailVerifiedForRegistration(string $email): EmailVerification
    {
        $withinMinutes = config('registration.complete_within_minutes');

        $verification = EmailVerification::query()
            ->where('email', $email)
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', now()->subMinutes($withinMinutes))
            ->latest('verified_at')
            ->first();

        if (! $verification) {
            throw new RegistrationException('Email is not verified or verification has expired. Verify your email again.', 422);
        }

        return $verification;
    }

    private function assertResendAllowed(string $email): void
    {
        $latest = EmailVerification::query()
            ->where('email', $email)
            ->latest()
            ->first();

        if (! $latest) {
            return;
        }

        $throttleSeconds = config('registration.resend_throttle_seconds');

        if ($latest->created_at->diffInSeconds(now()) < $throttleSeconds) {
            throw new RegistrationException(
                "Please wait {$throttleSeconds} seconds before requesting another code.",
                429
            );
        }
    }
}
