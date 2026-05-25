<?php

namespace App\Services\Governance;

use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\BackofficeRole;
use App\Models\User;
use App\Services\Backoffice\PermissionResolver;
use App\Services\Settlement\SettlementExceptionService;
use App\Services\Treasury\TreasuryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ApprovalRequestService
{
    public function __construct(
        private MakerCheckerService $makerChecker,
        private PermissionResolver $permissions,
        private TreasuryService $treasury,
    ) {}

    public function submit(
        User $maker,
        string $resource,
        string $resourceType,
        string $resourceId,
        array $payload,
        string $summary,
    ): ApprovalRequest {
        $policy = $this->makerChecker->assertMaker($maker, $resource);

        return ApprovalRequest::query()->create([
            'policy_id' => $policy->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'maker_id' => $maker->id,
            'status' => ApprovalRequestStatus::Pending,
            'summary' => $summary,
            'payload' => $payload,
        ]);
    }

    public function approve(ApprovalRequest $request, User $checker): ApprovalRequest
    {
        $this->assertPending($request);

        $request->loadMissing(['policy', 'maker']);
        $this->makerChecker->assertChecker($checker, $request->policy, $request->maker);

        $request->update([
            'status' => ApprovalRequestStatus::Approved,
            'checker_id' => $checker->id,
            'reviewed_at' => now(),
        ]);

        $this->applyApprovedPayload($request);

        // TODO: audit log — approval request approved

        return $request->fresh(['policy', 'maker', 'checker']);
    }

    public function reject(ApprovalRequest $request, User $checker, string $reason): ApprovalRequest
    {
        $this->assertPending($request);

        $request->loadMissing(['policy', 'maker']);
        $this->makerChecker->assertChecker($checker, $request->policy, $request->maker);

        $request->update([
            'status' => ApprovalRequestStatus::Rejected,
            'checker_id' => $checker->id,
            'checker_notes' => $reason,
            'reviewed_at' => now(),
        ]);

        // TODO: audit log — approval request rejected

        return $request->fresh(['policy', 'maker', 'checker']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPendingForChecker(User $checker, array $filters = []): LengthAwarePaginator
    {
        $checkerSlug = $this->roleSlug($checker);
        if (! $checkerSlug) {
            return ApprovalRequest::query()->whereRaw('1 = 0')->paginate(20);
        }

        $query = ApprovalRequest::query()
            ->with(['policy', 'maker.backofficeRole', 'checker'])
            ->where('status', ApprovalRequestStatus::Pending)
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $status = ApprovalRequestStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        $requests = $query->get()->filter(function (ApprovalRequest $request) use ($checkerSlug): bool {
            $request->loadMissing(['policy', 'maker.backofficeRole']);
            $makerSlug = $this->roleSlug($request->maker);

            if (! $makerSlug || ! $request->policy) {
                return false;
            }

            return in_array(
                $checkerSlug,
                $this->makerChecker->resolveCheckerRoles($request->policy, $makerSlug),
                true,
            );
        });

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $total = $requests->count();
        $items = $requests->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    public function find(int $id): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->with(['policy', 'maker.backofficeRole', 'checker'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->with(['policy', 'maker.backofficeRole', 'checker'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $status = ApprovalRequestStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    private function assertPending(ApprovalRequest $request): void
    {
        if ($request->status !== ApprovalRequestStatus::Pending) {
            throw new InvalidArgumentException('Only pending approval requests can be reviewed.');
        }
    }

    private function applyApprovedPayload(ApprovalRequest $request): void
    {
        $payload = $request->payload ?? [];

        if (($payload['action'] ?? null) === 'sync_role_permissions') {
            $role = BackofficeRole::query()->findOrFail($payload['role_id']);
            $this->permissions->syncRolePermissions($role, $payload['permissions'] ?? []);

            return;
        }

        if (($payload['action'] ?? null) === 'float_top_up') {
            $this->treasury->applyFloatTopUp(
                (int) $payload['float_position_id'],
                (float) $payload['amount'],
            );

            return;
        }

        if (($payload['action'] ?? null) === 'float_payout') {
            $this->treasury->applyFloatPayout(
                (int) $payload['float_position_id'],
                (float) $payload['amount'],
            );

            return;
        }

        if (($payload['action'] ?? null) === 'commission_batch_payout') {
            $this->treasury->applyCommissionBatchPayout((string) $payload['period']);
            return;
        }

        if (($payload['action'] ?? null) === 'settlement_manual_credit') {
            $exception = \App\Models\SettlementException::query()
                ->where('reference', $payload['exception_reference'] ?? $request->resource_id)
                ->firstOrFail();

            app(SettlementExceptionService::class)->executeCredit(
                $exception,
                $request->maker,
                (float) ($payload['amount'] ?? $exception->amount),
                [
                    'wallet_account' => $payload['wallet_account'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ],
                $request->checker,
            );
        }
    }

    private function roleSlug(User $user): ?string
    {
        $user->loadMissing('backofficeRole');

        return $user->backofficeRole?->slug
            ?? ($user->role?->value === 'admin' ? 'super_admin' : null);
    }
}
