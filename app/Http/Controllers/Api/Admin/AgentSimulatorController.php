<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Agent;
use App\Services\Agents\AgentTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AgentSimulatorController extends ApiController
{
    public function __construct(
        private AgentTransactionService $transactions,
    ) {}

    public function context(Agent $agent): JsonResponse
    {
        return $this->success($this->transactions->context($agent));
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:32'],
        ]);

        try {
            return $this->success($this->transactions->resolveAccount($validated['account_number']));
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cashIn(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'customer_account' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:1'],
            'terminal_id' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->transactions->cashIn(
                $agent,
                $validated['terminal_id'] ?? null,
                $validated['customer_account'],
                (float) $validated['amount'],
                auth('api')->user(),
                $validated['remark'] ?? null,
            );

            return $this->success($result, 'Cash-in completed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function cashOut(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'customer_account' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:1'],
            'terminal_id' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->transactions->cashOut(
                $agent,
                $validated['terminal_id'] ?? null,
                $validated['customer_account'],
                (float) $validated['amount'],
                auth('api')->user(),
                $validated['remark'] ?? null,
            );

            return $this->success($result, 'Cash-out completed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function walletTransfer(Request $request, Agent $agent): JsonResponse
    {
        $validated = $request->validate([
            'from_account' => ['required', 'string', 'max:32'],
            'to_account' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:1'],
            'terminal_id' => ['nullable', 'integer'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->transactions->walletTransfer(
                $agent,
                $validated['terminal_id'] ?? null,
                $validated['from_account'],
                $validated['to_account'],
                (float) $validated['amount'],
                auth('api')->user(),
                $validated['remark'] ?? null,
            );

            return $this->success($result, 'Wallet transfer completed.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
