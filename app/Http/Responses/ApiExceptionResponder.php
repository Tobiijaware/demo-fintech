<?php

namespace App\Http\Responses;

use App\Dtos\ApiResponseDTO;
use App\Exceptions\RegistrationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionResponder
{
    public static function respond(Throwable $e, Request $request): ?Response
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($e instanceof RegistrationException) {
            return ApiResponseDTO::error($e->getMessage(), $e->statusCode)->toResponse();
        }

        if ($e instanceof ValidationException) {
            return ApiResponseDTO::error($e->getMessage(), 422, $e->errors())->toResponse();
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponseDTO::error('Unauthenticated.', 401)->toResponse();
        }

        if ($e instanceof TokenExpiredException) {
            return ApiResponseDTO::error('Token has expired.', 401)->toResponse();
        }

        if ($e instanceof TokenInvalidException) {
            return ApiResponseDTO::error('Token is invalid.', 401)->toResponse();
        }

        if ($e instanceof TokenBlacklistedException) {
            return ApiResponseDTO::error('Token has been blacklisted.', 401)->toResponse();
        }

        if ($e instanceof JWTException) {
            return ApiResponseDTO::error($e->getMessage() ?: 'Could not process token.', 401)->toResponse();
        }

        if ($e instanceof ModelNotFoundException) {
            return ApiResponseDTO::error('Resource not found.', 404)->toResponse();
        }

        if ($e instanceof NotFoundHttpException) {
            return ApiResponseDTO::error('Endpoint not found.', 404)->toResponse();
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return ApiResponseDTO::error('Too many requests.', 429)->toResponse();
        }

        if ($e instanceof HttpException) {
            return ApiResponseDTO::error(
                Response::$statusTexts[$e->getStatusCode()] ?? 'Request failed.',
                $e->getStatusCode(),
            )->toResponse();
        }

        if (config('app.debug')) {
            return null;
        }

        return ApiResponseDTO::error('Server error.', 500)->toResponse();
    }
}
