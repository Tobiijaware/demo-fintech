<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\RejectApprovalRequestRequest;
use App\Models\ApprovalRequest;
use App\Services\Governance\ApprovalRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestController extends ApiController
{
    public function __construct(private ApprovalRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'per_page', 'page']);

        $paginator = $request->query('scope') === 'checker'
            ? $this->service->listPendingForChecker(auth('api')->user(), $filters)
            : $this->service->list($filters);

        $paginator->getCollection()->transform(fn (ApprovalRequest $item) => $this->format($item));

        return $this->success($paginator);
    }

    public function show(ApprovalRequest $approvalRequest): JsonResponse
    {
        return $this->success($this->format($this->service->find($approvalRequest->id), true));
    }

    public function approve(ApprovalRequest $approvalRequest): JsonResponse
    {
        $request = $this->service->approve($approvalRequest, auth('api')->user());

        return $this->success($this->format($request, true), 'Approval request approved.');
    }

    public function reject(ApprovalRequest $approvalRequest, RejectApprovalRequestRequest $request): JsonResponse
    {
        $item = $this->service->reject(
            $approvalRequest,
            auth('api')->user(),
            $request->validated('reason'),
        );

        return $this->success($this->format($item, true), 'Approval request rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ApprovalRequest $request, bool $detailed = false): array
    {
        $request->loadMissing(['policy', 'maker.backofficeRole', 'checker']);

        $data = [
            'id' => $request->id,
            'policy_id' => $request->policy_id,
            'policy' => $request->policy ? [
                'id' => $request->policy->id,
                'action' => $request->policy->action,
                'resource' => $request->policy->resource,
            ] : null,
            'resource_type' => $request->resource_type,
            'resource_id' => $request->resource_id,
            'status' => $request->status->value,
            'summary' => $request->summary,
            'maker' => $request->maker ? [
                'id' => $request->maker->id,
                'name' => $request->maker->full_name,
                'role_slug' => $request->maker->backofficeRole?->slug,
            ] : null,
            'checker' => $request->checker ? [
                'id' => $request->checker->id,
                'name' => $request->checker->full_name,
            ] : null,
            'checker_notes' => $request->checker_notes,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['payload'] = $request->payload;
        }

        return $data;
    }
}
