<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreMakerCheckerPolicyRequest;
use App\Http\Requests\Admin\UpdateMakerCheckerPolicyRequest;
use App\Models\MakerCheckerPolicy;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;

class MakerCheckerPolicyController extends ApiController
{
    public function __construct(private AuditLogService $auditLog) {}
    public function index(): JsonResponse
    {
        $policies = MakerCheckerPolicy::query()
            ->orderBy('department')
            ->orderBy('sort_order')
            ->orderBy('action')
            ->get()
            ->map(fn (MakerCheckerPolicy $policy) => $this->format($policy));

        return $this->success($policies);
    }

    public function show(MakerCheckerPolicy $makerCheckerPolicy): JsonResponse
    {
        return $this->success($this->format($makerCheckerPolicy));
    }

    public function store(StoreMakerCheckerPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role_pairs'] = $this->normalizeRolePairs($data['role_pairs']);
        $data['enforced'] = $data['enforced'] ?? true;
        $data['enforcement'] = $data['enforcement'] ?? 'policy';
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $policy = MakerCheckerPolicy::query()->create($data);

        $formatted = $this->format($policy);

        $this->auditLog->record(
            auth('api')->user(),
            'maker_checker_policy.created',
            'MakerCheckerPolicy',
            $policy->id,
            "Created maker-checker policy {$policy->action} ({$policy->department})",
            ['after' => $formatted],
        );

        return $this->success($formatted, 'Maker-checker policy created.', 201);
    }

    public function update(UpdateMakerCheckerPolicyRequest $request, MakerCheckerPolicy $makerCheckerPolicy): JsonResponse
    {
        $before = $this->format($makerCheckerPolicy);

        $data = $request->validated();

        if (isset($data['role_pairs'])) {
            $data['role_pairs'] = $this->normalizeRolePairs($data['role_pairs']);
        }

        $makerCheckerPolicy->update($data);

        $after = $this->format($makerCheckerPolicy->fresh());

        $this->auditLog->record(
            auth('api')->user(),
            'maker_checker_policy.updated',
            'MakerCheckerPolicy',
            $makerCheckerPolicy->id,
            "Updated maker-checker policy {$makerCheckerPolicy->action}",
            ['before' => $before, 'after' => $after],
        );

        return $this->success($after, 'Maker-checker policy updated.');
    }

    public function destroy(MakerCheckerPolicy $makerCheckerPolicy): JsonResponse
    {
        $before = $this->format($makerCheckerPolicy);

        $this->auditLog->record(
            auth('api')->user(),
            'maker_checker_policy.deleted',
            'MakerCheckerPolicy',
            $makerCheckerPolicy->id,
            "Deleted maker-checker policy {$makerCheckerPolicy->action}",
            ['before' => $before],
        );

        $makerCheckerPolicy->delete();

        return $this->success(null, 'Maker-checker policy deleted.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $pairs
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRolePairs(array $pairs): array
    {
        return array_values(array_map(static function (array $pair): array {
            return [
                'id' => $pair['id'],
                'label' => $pair['label'],
                'maker_roles' => array_values(array_unique($pair['maker_roles'] ?? [])),
                'checker_roles' => array_values(array_unique($pair['checker_roles'] ?? [])),
            ];
        }, $pairs));
    }

    /**
     * @return array<string, mixed>
     */
    private function format(MakerCheckerPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'department' => $policy->department,
            'action' => $policy->action,
            'description' => $policy->description,
            'resource' => $policy->resource,
            'threshold' => $policy->threshold,
            'enforced' => $policy->enforced,
            'enforcement' => $policy->enforcement,
            'role_pairs' => $policy->role_pairs ?? [],
            'sort_order' => $policy->sort_order,
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
