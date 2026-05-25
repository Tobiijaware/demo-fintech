<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->auditLogService->list($request->only([
            'action',
            'actor',
            'resource_type',
            'from',
            'to',
            'search',
            'per_page',
        ]));

        $paginator->getCollection()->transform(fn (AuditLog $log) => $this->format($log));

        return $this->success($paginator);
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->auditLogService->stats());
    }

    /**
     * @return array<string, mixed>
     */
    private function format(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'actor_id' => $log->actor_id,
            'actor_email' => $log->actor_email,
            'actor_role_slug' => $log->actor_role_slug,
            'action' => $log->action,
            'resource_type' => $log->resource_type,
            'resource_id' => $log->resource_id,
            'summary' => $log->summary,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }
}
