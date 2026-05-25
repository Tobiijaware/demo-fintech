<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\ReviewProvisioningRequestRequest;
use App\Enums\ProvisioningRequestType;
use App\Models\ProvisioningRequest;
use App\Services\Governance\ProvisioningRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProvisioningRequestController extends ApiController
{
    public function __construct(private ProvisioningRequestService $provisioning) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'type', 'per_page']);

        $paginator = ($request->query('status') === 'pending' || $request->boolean('pending_only'))
            ? $this->provisioning->listPending($filters)
            : $this->provisioning->list($filters);

        $paginator->getCollection()->transform(fn (ProvisioningRequest $item) => $this->format($item));

        return $this->success($paginator);
    }

    public function show(string $provisioningRequest): JsonResponse
    {
        $request = $this->provisioning->findByReference($provisioningRequest);

        return $this->success($this->format($request));
    }

    public function approve(string $provisioningRequest): JsonResponse
    {
        try {
            $request = $this->provisioning->findByReference($provisioningRequest);
            $updated = $this->provisioning->approve($request, auth('api')->user());

            return $this->success($this->format($updated), 'Provisioning request approved.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function reject(ReviewProvisioningRequestRequest $httpRequest, string $provisioningRequest): JsonResponse
    {
        try {
            $request = $this->provisioning->findByReference($provisioningRequest);
            $updated = $this->provisioning->reject(
                $request,
                auth('api')->user(),
                $httpRequest->validated('notes'),
            );

            return $this->success($this->format($updated), 'Provisioning request rejected.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function returnForClarification(ReviewProvisioningRequestRequest $httpRequest, string $provisioningRequest): JsonResponse
    {
        $notes = $httpRequest->validated('notes');
        if (! $notes) {
            return $this->error('Notes are required when returning a request.', 422);
        }

        try {
            $request = $this->provisioning->findByReference($provisioningRequest);
            $updated = $this->provisioning->returnForClarification(
                $request,
                auth('api')->user(),
                $notes,
            );

            return $this->success($this->format($updated), 'Provisioning request returned for clarification.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ProvisioningRequest $request): array
    {
        $request->loadMissing(['requestedBy.backofficeRole', 'checker']);

        $subject = $request->subject ?? [];
        $userDetails = is_array($subject['user_details'] ?? null) ? $subject['user_details'] : [];

        return [
            'reference' => $request->reference,
            'type' => $request->type->value,
            'status' => $request->status->value,
            'title' => $subject['title'] ?? $request->reference,
            'sub' => $subject['sub'] ?? null,
            'role' => $subject['role'] ?? ($request->requestedBy?->backofficeRole?->name ?? '—'),
            'region' => $subject['region'] ?? null,
            'action' => $request->type === ProvisioningRequestType::RoleChange ? 'Review' : 'Provision',
            'status_label' => ucfirst(str_replace('_', ' ', $request->status->value)),
            'role_badge_tone' => $request->type === ProvisioningRequestType::RoleChange ? 'purple' : 'blue',
            'requested_by' => $request->requestedBy?->full_name ?? '—',
            'requested_by_title' => $request->requestedBy?->job_title ?? '—',
            'request_note' => $subject['request_note'] ?? $request->notes,
            'duration' => $subject['duration'] ?? null,
            'reports_to' => $subject['reports_to'] ?? null,
            'user_details' => [
                'name' => $userDetails['name'] ?? '—',
                'email' => $userDetails['email'] ?? '',
                'staff_id' => $userDetails['staff_id'] ?? '—',
                'hire_date' => $userDetails['hire_date'] ?? '—',
                'training' => $userDetails['training'] ?? '—',
                'background_check' => $userDetails['background_check'] ?? '—',
            ],
            'permissions' => $subject['permissions'] ?? [],
            'permission_note' => $subject['permission_note'] ?? null,
            'mfa_required' => (bool) ($subject['mfa_required'] ?? true),
            'password_expiry' => (bool) ($subject['password_expiry'] ?? true),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }
}
