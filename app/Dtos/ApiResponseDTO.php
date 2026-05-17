<?php

namespace App\Dtos;

use Illuminate\Http\JsonResponse;

class ApiResponseDTO
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public int $statusCode = 200,
    ) {}

    public static function success(mixed $data = null, ?string $message = null, int $statusCode = 200): self
    {
        return new self(true, $data, $message, $statusCode);
    }

    public static function error(?string $message = null, int $statusCode = 400, mixed $data = null): self
    {
        return new self(false, $data, $message, $statusCode);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'success' => $this->success,
            'data' => $this->data,
            'message' => $this->message,
        ], $this->statusCode);
    }
}
