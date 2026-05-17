<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Dtos\ApiResponseDTO;
use Illuminate\Http\JsonResponse;

trait RespondsWithApiDto
{
    protected function success(mixed $data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        return ApiResponseDTO::success($data, $message, $statusCode)->toResponse();
    }

    protected function error(?string $message = null, int $statusCode = 400, mixed $data = null): JsonResponse
    {
        return ApiResponseDTO::error($message, $statusCode, $data)->toResponse();
    }
}
