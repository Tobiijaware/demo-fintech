<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\StrFiling;
use App\Services\Aml\StrFilingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StrFilingController extends ApiController
{
    public function __construct(
        private StrFilingService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->service->list($request->only([
            'status', 'search', 'per_page',
        ]));

        return $this->success([
            'items' => collect($paginator->items())->map(fn (StrFiling $filing) => $this->format($filing)),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(StrFiling $strFiling): JsonResponse
    {
        $filing = $this->service->find($strFiling->reference);

        return $this->success($this->format($filing, true));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'case_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'narrative' => ['nullable', 'string'],
            'amount_ngn' => ['nullable', 'numeric', 'min:0'],
        ]);

        $filing = $this->service->createDraft($validated, auth('api')->user());

        return $this->success($this->format($filing, true), 'STR draft created.', 201);
    }

    public function submit(Request $request, StrFiling $strFiling): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->submitForReview(
            $strFiling,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated, true), 'STR submitted for compliance review.');
    }

    public function approve(Request $request, StrFiling $strFiling): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->approve(
            $strFiling,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated, true), 'STR approved and submitted to NFIU.');
    }

    public function reject(Request $request, StrFiling $strFiling): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $updated = $this->service->reject(
            $strFiling,
            auth('api')->user(),
            $validated['notes'] ?? null,
        );

        return $this->success($this->format($updated, true), 'STR rejected.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function format(StrFiling $filing, bool $detailed = false): array
    {
        $data = [
            'id' => $filing->reference,
            'reference' => $filing->reference,
            'case_id' => $filing->amlCase?->reference,
            'subject' => $filing->title,
            'amount_ngn' => (float) $filing->amount_ngn,
            'filed_by' => $filing->maker?->full_name,
            'maker_id' => $filing->maker_id,
            'checker' => $filing->checker?->full_name,
            'filed_at' => $filing->submitted_at?->toIso8601String(),
            'status' => $filing->status->label(),
            'nfiu_ref' => $filing->nfiu_reference,
            'created_at' => $filing->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['narrative'] = $filing->narrative;
        }

        return $data;
    }
}
