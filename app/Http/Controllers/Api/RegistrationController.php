<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Registration\CompleteRegistrationRequest;
use App\Http\Requests\Registration\ResendCodeRequest;
use App\Http\Requests\Registration\StartRegistrationRequest;
use App\Http\Requests\Registration\VerifyEmailRequest;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends ApiController
{
    public function __construct(private RegistrationService $registrationService) {}

    public function sendCode(StartRegistrationRequest $request): JsonResponse
    {
        $this->registrationService->sendVerificationCode($request->validated('email'));

        return $this->success(null, 'Verification code sent to your email.');
    }

    public function resendCode(ResendCodeRequest $request): JsonResponse
    {
        $this->registrationService->resendVerificationCode($request->validated('email'));

        return $this->success(null, 'A new verification code has been sent.');
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $data = $this->registrationService->verifyEmail(
            $request->validated('email'),
            $request->validated('code'),
        );

        return $this->success($data, 'Email verified successfully. Complete your account setup.');
    }

    public function complete(CompleteRegistrationRequest $request): JsonResponse
    {
        $data = $this->registrationService->completeRegistration($request->validated());

        return $this->success($data, 'Account created successfully.', 201);
    }
}
