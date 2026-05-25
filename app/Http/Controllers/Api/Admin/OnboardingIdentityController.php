<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Onboarding\OnboardingIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OnboardingIdentityController extends ApiController
{
    public function __construct(
        private OnboardingIdentityService $identity,
    ) {}

    public function verifyBvn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bvn' => ['required', 'string', 'max:32'],
            'firstname' => ['required', 'string', 'max:128'],
            'lastname' => ['required', 'string', 'max:128'],
        ]);

        try {
            return $this->success($this->identity->verifyBvn(
                $validated['bvn'],
                $validated['firstname'],
                $validated['lastname'],
            ));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function verifyNin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nin' => ['required', 'string', 'max:32'],
        ]);

        try {
            return $this->success($this->identity->verifyNin($validated['nin']));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
