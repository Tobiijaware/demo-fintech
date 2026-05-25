<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ComplianceAuditFinding;
use App\Models\CompliancePolicy;
use App\Models\Regulator;
use App\Models\RegulatoryFiling;
use App\Services\Backoffice\PermissionResolver;
use App\Services\Compliance\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ComplianceController extends ApiController
{
    public function __construct(
        private ComplianceService $service,
        private PermissionResolver $permissions,
    ) {}

    public function stats(): JsonResponse
    {
        return $this->success($this->service->stats());
    }

    public function indexFilings(Request $request): JsonResponse
    {
        $paginator = $this->service->listFilings($request->only([
            'status', 'regulator', 'search', 'due_this_week', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (RegulatoryFiling $f) => $this->formatFiling($f)),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storeFiling(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:32', 'unique:regulatory_filings,reference'],
            'title' => ['required', 'string', 'max:255'],
            'regulator' => ['required', 'string', 'max:16'],
            'due_date' => ['required_without:dueIso', 'date'],
            'dueIso' => ['required_without:due_date', 'date'],
            'status' => ['nullable', 'string', 'max:32'],
            'owner' => ['required_without:owner_name', 'string', 'max:255'],
            'owner_name' => ['required_without:owner', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'frequency' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'sub' => ['nullable', 'string'],
        ]);

        $filing = $this->service->createFiling($validated);

        return $this->success($this->formatFiling($filing, true), 'Regulatory filing created.', 201);
    }

    public function showFiling(RegulatoryFiling $regulatoryFiling): JsonResponse
    {
        $regulatoryFiling->load('owner');

        return $this->success($this->formatFiling($regulatoryFiling, true));
    }

    public function updateFiling(Request $request, RegulatoryFiling $regulatoryFiling): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'regulator' => ['sometimes', 'string', 'max:16'],
            'due_date' => ['sometimes', 'date'],
            'dueIso' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'max:32'],
            'owner' => ['sometimes', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'frequency' => ['sometimes', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'sub' => ['nullable', 'string'],
        ]);

        $filing = $this->service->updateFiling($regulatoryFiling, $validated);

        return $this->success($this->formatFiling($filing, true), 'Regulatory filing updated.');
    }

    public function submitFiling(RegulatoryFiling $regulatoryFiling): JsonResponse
    {
        try {
            $filing = $this->service->submitFiling($regulatoryFiling, auth('api')->user());
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->formatFiling($filing, true), 'Filing marked as submitted.');
    }

    public function indexRegulators(): JsonResponse
    {
        return $this->success([
            'items' => $this->service->listRegulators()->map(fn (Regulator $r) => $this->formatRegulator($r)),
        ]);
    }

    public function showRegulator(Regulator $regulator): JsonResponse
    {
        return $this->success($this->formatRegulator($regulator));
    }

    public function indexAuditFindings(Request $request): JsonResponse
    {
        $paginator = $this->service->listAuditFindings($request->only([
            'status', 'severity', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (ComplianceAuditFinding $f) => $this->formatFinding($f)),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function showAuditFinding(ComplianceAuditFinding $complianceAuditFinding): JsonResponse
    {
        return $this->success($this->formatFinding($complianceAuditFinding, true));
    }

    public function updateAuditFinding(Request $request, ComplianceAuditFinding $complianceAuditFinding): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:32'],
            'remediation_notes' => ['nullable', 'string'],
        ]);

        $finding = $this->service->updateAuditFinding(
            $complianceAuditFinding,
            $validated,
            auth('api')->user(),
        );

        return $this->success($this->formatFinding($finding, true), 'Audit finding updated.');
    }

    public function indexPolicies(Request $request): JsonResponse
    {
        if (! $this->canAccessPolicies($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $paginator = $this->service->listPolicies($request->only([
            'category', 'status', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (CompliancePolicy $p) => $this->formatPolicy($p)),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function showPolicy(Request $request, string $reference): JsonResponse
    {
        if (! $this->canAccessPolicies($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $policy = $this->service->findPolicy($reference);

        return $this->success($this->formatPolicy($policy, true));
    }

    public function downloadPolicy(Request $request, string $reference)
    {
        if (! $this->canAccessPolicies($request)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $policy = $this->service->findPolicy($reference);

        return $this->service->downloadPolicy($policy);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatFiling(RegulatoryFiling $filing, bool $detailed = false): array
    {
        $data = [
            'id' => $filing->reference,
            'reference' => $filing->reference,
            'title' => $filing->title,
            'sub' => $filing->description,
            'description' => $filing->description,
            'regulator' => $filing->regulator,
            'due' => $filing->due_date?->format('j M Y'),
            'dueIso' => $filing->due_date?->toDateString(),
            'due_date' => $filing->due_date?->toDateString(),
            'status' => $filing->status->label(),
            'status_code' => $filing->status->value,
            'owner' => $filing->owner_name,
            'owner_name' => $filing->owner_name,
            'owner_id' => $filing->owner_id,
            'frequency' => $filing->frequency,
            'submitted_at' => $filing->submitted_at?->toIso8601String(),
            'created_at' => $filing->created_at?->toIso8601String(),
            'updated_at' => $filing->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['owner_user'] = $filing->relationLoaded('owner') && $filing->owner
                ? [
                    'id' => $filing->owner->id,
                    'email' => $filing->owner->email,
                    'name' => trim("{$filing->owner->firstname} {$filing->owner->lastname}"),
                ]
                : null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatRegulator(Regulator $regulator): array
    {
        return [
            'code' => $regulator->code,
            'name' => $regulator->name,
            'status' => $regulator->status,
            'lastSubmission' => $regulator->last_submission?->format('j M Y'),
            'last_submission' => $regulator->last_submission?->toDateString(),
            'nextDue' => $regulator->next_due?->format('j M Y'),
            'next_due' => $regulator->next_due?->toDateString(),
            'contact' => $regulator->contact_email,
            'contact_email' => $regulator->contact_email,
            'filingsYtd' => $regulator->filings_ytd,
            'filings_ytd' => $regulator->filings_ytd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatFinding(ComplianceAuditFinding $finding, bool $detailed = false): array
    {
        $data = [
            'id' => $finding->reference,
            'reference' => $finding->reference,
            'area' => $finding->area,
            'title' => $finding->title,
            'severity' => $finding->severity->label(),
            'severity_code' => $finding->severity->value,
            'status' => $finding->status->label(),
            'status_code' => $finding->status->value,
            'owner' => $finding->owner,
            'due' => $finding->due_date?->format('j M Y'),
            'due_date' => $finding->due_date?->toDateString(),
            'opened' => $finding->opened_at?->format('j M Y'),
            'opened_at' => $finding->opened_at?->toDateString(),
        ];

        if ($detailed) {
            $data['remediation_notes'] = $finding->remediation_notes;
            $data['created_at'] = $finding->created_at?->toIso8601String();
            $data['updated_at'] = $finding->updated_at?->toIso8601String();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatPolicy(CompliancePolicy $policy, bool $detailed = false): array
    {
        $data = [
            'id' => $policy->reference,
            'reference' => $policy->reference,
            'name' => $policy->name,
            'version' => $policy->version,
            'owner' => $policy->owner,
            'effective' => $policy->effective_date?->format('j M Y'),
            'effective_date' => $policy->effective_date?->toDateString(),
            'reviewDue' => $policy->review_due?->format('j M Y'),
            'reviewDueIso' => $policy->review_due?->toDateString(),
            'review_due' => $policy->review_due?->toDateString(),
            'status' => $policy->status->label(),
            'status_code' => $policy->status->value,
            'category' => $policy->category->label(),
            'category_code' => $policy->category->value,
            'summary' => $policy->summary,
            'has_document' => (bool) $policy->document_path,
        ];

        if ($detailed) {
            $data['document_path'] = $policy->document_path;
            $data['created_at'] = $policy->created_at?->toIso8601String();
            $data['updated_at'] = $policy->updated_at?->toIso8601String();
        }

        return $data;
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<mixed>  $paginator
     * @return array<string, int>
     */
    protected function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    protected function canAccessPolicies(Request $request): bool
    {
        $user = $request->user('api');

        if (! $user) {
            return false;
        }

        return $this->permissions->can($user, 'regulatory_filings')
            || $this->permissions->can($user, 'user_management');
    }
}
