<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\SanctionsHit;
use App\Services\Aml\SanctionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmlSanctionsController extends ApiController
{
    public function __construct(
        private SanctionsService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (SanctionsHit $hit) => $this->format($hit)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(SanctionsHit $sanctionsHit): JsonResponse
    {
        $hit = $this->service->find($sanctionsHit->reference);

        return $this->success($this->format($hit));
    }

    public function falsePositive(Request $request, SanctionsHit $sanctionsHit): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->markFalsePositive(
            $sanctionsHit,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated), 'Sanction hit marked as false positive.');
    }

    public function confirm(Request $request, SanctionsHit $sanctionsHit): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->confirmMatch(
            $sanctionsHit,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated), 'Sanction hit confirmed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(SanctionsHit $hit): array
    {
        return [
            'id' => $hit->reference,
            'reference' => $hit->reference,
            'list' => $hit->list_name,
            'matched_name' => $hit->matched_name,
            'subject_type' => ucfirst($hit->subject_type),
            'subject_id' => $hit->subject_id,
            'match_score' => $hit->match_score,
            'status' => $hit->status->label(),
            'screened_at' => $hit->created_at?->toIso8601String(),
            'analyst' => $hit->reviewedBy?->full_name,
        ];
    }
}
