<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{
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

        return $this->success($user);
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
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => $user,
        ];
    }
}
