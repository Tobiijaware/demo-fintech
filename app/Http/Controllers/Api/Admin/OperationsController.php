<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Operations\OperationsService;
use Illuminate\Http\JsonResponse;

class OperationsController extends ApiController
{
    public function __construct(
        private OperationsService $service,
    ) {}

    public function dashboard(): JsonResponse
    {
        return $this->success($this->service->dashboard());
    }

    public function channels(): JsonResponse
    {
        return $this->success([
            'items' => $this->service->channels(),
        ]);
    }

    public function partners(): JsonResponse
    {
        return $this->success([
            'items' => $this->service->partners(),
        ]);
    }
}
