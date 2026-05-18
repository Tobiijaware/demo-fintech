<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetupPinRequest;
use App\Models\User;
use App\Services\Auth\PinService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{
    public function __construct(private PinService $pinService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $token = auth('api')->attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials.', 401);
        }

        return $this->success($this->tokenPayload($token, auth('api')->user()), 'Login successful.');
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user()->load(['wallet', 'kycVerifications']);
        $user->append('pin_set_up');

        return $this->success($user);
    }

    public function setupPin(SetupPinRequest $request): JsonResponse
    {
        $this->pinService->setup(auth('api')->user(), $request->validated('pin'));

        return $this->success(['pin_set_up' => true], 'Transaction PIN set successfully.', 201);
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();

        return $this->success(
            $this->tokenPayload($token, auth('api')->user()),
            'Token refreshed.',
        );
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(null, 'Successfully logged out.');
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(string $token, User $user): array
    {
        $user->append('pin_set_up');

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => $user,
        ];
    }
}
