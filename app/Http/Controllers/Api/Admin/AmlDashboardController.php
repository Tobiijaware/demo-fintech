<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Aml\AmlDashboardService;
use Illuminate\Http\JsonResponse;

class AmlDashboardController extends ApiController
{
    public function __construct(
        private AmlDashboardService $service,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }
}
