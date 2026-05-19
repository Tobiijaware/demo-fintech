<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Bank\ResolveAccountRequest;
use App\Services\Dojah\DojahClient;
use Illuminate\Http\JsonResponse;

class BankController extends ApiController
{
    public function __construct(private DojahClient $dojahClient) {}

    public function index(): JsonResponse
    {
        return $this->success(
            ['banks' => $this->dojahClient->getBanks()],
            'Banks retrieved successfully.',
        );
    }

    public function resolve(ResolveAccountRequest $request): JsonResponse
    {
        $entity = $this->dojahClient->resolveAccount(
            $request->validated('account_number'),
            $request->validated('bank_code'),
        );

        return $this->success(['entity' => $entity], 'Account resolved successfully.');
    }
}
