<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Agents\AgentAnalyticsService;
use Illuminate\Http\JsonResponse;

class AgentAnalyticsController extends ApiController
{
    public function __construct(
        private AgentAnalyticsService $service,
    ) {}

    public function performance(): JsonResponse
    {
        return $this->success($this->service->performance());
    }

    public function regions(): JsonResponse
    {
        return $this->success($this->service->regions());
    }
}
