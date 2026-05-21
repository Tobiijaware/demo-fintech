<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Api\Concerns\SerializesBackofficeUser;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SetupPinRequest;
use App\Models\User;
use App\Services\Auth\PinService;
use App\Services\Kyc\CustomerKycService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{
    use SerializesBackofficeUser;

    public function __construct(
        private PinService $pinService,
        private CustomerKycService $customerKycService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $token = auth('api')->attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials.', 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        if ($request->boolean('backoffice') || $request->header('X-Backoffice-Client')) {
            if (! $user->isBackofficeStaff()) {
                auth('api')->logout();

                return $this->error('This account is not authorized for the back office.', 403);
            }
        }

        return $this->success($this->tokenPayload($token, $user), 'Login successful.');
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user()->load(['wallet', 'kycVerifications', 'backofficeRole']);
        $user->append('pin_set_up');

        $payload = $user->toArray();
        $profile = $this->backofficeProfile($user);
        if ($profile) {
            $payload['backoffice'] = $profile;
        }

        if ($user->user_type === UserType::Customer) {
            $payload['kyc_progress'] = $this->customerKycService->progress($user);
            $payload['kyc_status'] = $payload['kyc_progress']['kyc_status'];
            $payload['account_tier'] = $payload['kyc_progress']['account_tier'];
        }

        return $this->success($payload);
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
        $user->loadMissing('backofficeRole');
        $user->append('pin_set_up');

        $payload = [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => $user,
        ];

        $profile = $this->backofficeProfile($user);
        if ($profile) {
            $payload['backoffice'] = $profile;
        }

        return $payload;
    }
}
