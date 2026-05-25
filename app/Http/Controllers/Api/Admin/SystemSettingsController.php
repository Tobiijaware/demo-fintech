<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\Governance\SystemSettingsService;
use Illuminate\Http\JsonResponse;

class SystemSettingsController extends ApiController
{
    public function __construct(private SystemSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success([
            'settings' => $this->settings->getAllGrouped(),
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $grouped = $this->settings->updateBatch(
            auth('api')->user(),
            $request->validated('settings'),
        );

        return $this->success([
            'settings' => $grouped,
        ], 'Settings updated.');
    }

    public function integrations(): JsonResponse
    {
        return $this->success([
            'integrations' => $this->settings->integrationHealth(),
        ]);
    }
}
