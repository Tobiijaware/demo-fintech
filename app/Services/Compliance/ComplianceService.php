<?php

namespace App\Services\Compliance;

use App\Enums\FilingStatus;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\PolicyCategory;
use App\Enums\PolicyStatus;
use App\Models\ComplianceAuditFinding;
use App\Models\CompliancePolicy;
use App\Models\Regulator;
use App\Models\RegulatoryFiling;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        return [
            'filings_due_this_week' => RegulatoryFiling::query()
                ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->whereNot('status', FilingStatus::Submitted)
                ->count(),
            'open_findings' => ComplianceAuditFinding::query()
                ->whereIn('status', [FindingStatus::Open, FindingStatus::InProgress])
                ->count(),
            'policies_review_due' => CompliancePolicy::query()
                ->where('status', PolicyStatus::ReviewDue)
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listFilings(array $filters = []): LengthAwarePaginator
    {
        $query = RegulatoryFiling::query()
            ->with('owner')
            ->orderBy('due_date');

        if (! empty($filters['status'])) {
            $query->where('status', $this->parseFilingStatus($filters['status']));
        }

        if (! empty($filters['regulator'])) {
            $query->where('regulator', strtoupper($filters['regulator']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['due_this_week'])) {
            $query->whereBetween('due_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ]);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findFiling(string $reference): RegulatoryFiling
    {
        return RegulatoryFiling::query()
            ->with('owner')
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFiling(array $data): RegulatoryFiling
    {
        return RegulatoryFiling::query()->create([
            'reference' => $data['reference'] ?? $this->generateFilingReference(),
            'title' => $data['title'],
            'regulator' => strtoupper($data['regulator']),
            'due_date' => Carbon::parse($data['due_date'] ?? $data['due_date_iso'] ?? $data['dueIso']),
            'status' => $this->parseFilingStatus($data['status'] ?? FilingStatus::Draft),
            'owner_name' => $data['owner_name'] ?? $data['owner'],
            'owner_id' => $data['owner_id'] ?? null,
            'frequency' => $data['frequency'],
            'description' => $data['description'] ?? $data['sub'] ?? null,
            'submitted_at' => null,
        ])->fresh(['owner']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFiling(RegulatoryFiling $filing, array $data): RegulatoryFiling
    {
        $payload = [];

        foreach (['title', 'regulator', 'owner_name', 'owner_id', 'frequency', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (isset($data['sub']) && ! isset($data['description'])) {
            $payload['description'] = $data['sub'];
        }

        if (isset($data['owner']) && ! isset($data['owner_name'])) {
            $payload['owner_name'] = $data['owner'];
        }

        if (isset($data['regulator'])) {
            $payload['regulator'] = strtoupper($data['regulator']);
        }

        if (isset($data['due_date']) || isset($data['due_date_iso']) || isset($data['dueIso'])) {
            $payload['due_date'] = Carbon::parse($data['due_date'] ?? $data['due_date_iso'] ?? $data['dueIso']);
        }

        if (isset($data['status'])) {
            $payload['status'] = $this->parseFilingStatus($data['status']);
        }

        $filing->update($payload);

        return $filing->fresh(['owner']);
    }

    public function submitFiling(RegulatoryFiling $filing, User $actor): RegulatoryFiling
    {
        if ($filing->status === FilingStatus::Submitted) {
            throw new InvalidArgumentException('Filing is already submitted.');
        }

        $filing->update([
            'status' => FilingStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->auditLog->record(
            $actor,
            'compliance.filing.submitted',
            'RegulatoryFiling',
            $filing->reference,
            "Submitted regulatory filing {$filing->reference}: {$filing->title}",
            [
                'reference' => $filing->reference,
                'regulator' => $filing->regulator,
                'title' => $filing->title,
            ],
        );

        return $filing->fresh(['owner']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Regulator>
     */
    public function listRegulators()
    {
        return Regulator::query()->orderBy('code')->get();
    }

    public function findRegulator(string $code): Regulator
    {
        return Regulator::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listAuditFindings(array $filters = []): LengthAwarePaginator
    {
        $query = ComplianceAuditFinding::query()->orderByDesc('opened_at');

        if (! empty($filters['status'])) {
            $query->where('status', $this->parseFindingStatus($filters['status']));
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $this->parseFindingSeverity($filters['severity']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('area', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findAuditFinding(string $reference): ComplianceAuditFinding
    {
        return ComplianceAuditFinding::query()
            ->where('reference', $reference)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAuditFinding(ComplianceAuditFinding $finding, array $data, User $actor): ComplianceAuditFinding
    {
        $payload = [];

        if (isset($data['status'])) {
            $payload['status'] = $this->parseFindingStatus($data['status']);
        }

        if (array_key_exists('remediation_notes', $data)) {
            $payload['remediation_notes'] = $data['remediation_notes'];
        }

        $finding->update($payload);

        if (isset($payload['status']) || array_key_exists('remediation_notes', $data)) {
            $this->auditLog->record(
                $actor,
                'compliance.finding.remediated',
                'ComplianceAuditFinding',
                $finding->reference,
                "Updated audit finding {$finding->reference}",
                [
                    'reference' => $finding->reference,
                    'status' => $finding->status->value,
                    'remediation_notes' => $finding->remediation_notes,
                ],
            );
        }

        return $finding->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPolicies(array $filters = []): LengthAwarePaginator
    {
        $query = CompliancePolicy::query()->orderBy('reference');

        if (! empty($filters['category'])) {
            $query->where('category', $this->parsePolicyCategory($filters['category']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $this->parsePolicyStatus($filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50));
    }

    public function findPolicy(string $reference): CompliancePolicy
    {
        return CompliancePolicy::query()
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function downloadPolicy(CompliancePolicy $policy): StreamedResponse|Response
    {
        if ($policy->document_path && is_file($policy->document_path)) {
            return response()->download($policy->document_path, basename($policy->document_path));
        }

        $placeholder = "Policy document placeholder\n\n{$policy->reference} — {$policy->name} v{$policy->version}\n";

        return response()->streamDownload(
            function () use ($placeholder) {
                echo $placeholder;
            },
            "{$policy->reference}.txt",
            ['Content-Type' => 'text/plain'],
        );
    }

    public function generateFilingReference(): string
    {
        $year = now()->format('Y');
        $latest = RegulatoryFiling::query()
            ->where('reference', 'like', "FIL-{$year}-%")
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = 1;

        if ($latest && preg_match('/^FIL-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('FIL-%s-%03d', $year, $sequence);
            $sequence++;
        } while (RegulatoryFiling::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function parseFilingStatus(mixed $status): FilingStatus
    {
        if ($status instanceof FilingStatus) {
            return $status;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));

        return FilingStatus::tryFrom($normalized)
            ?? match ($normalized) {
                'in_review', 'inreview' => FilingStatus::InReview,
                default => FilingStatus::from($normalized),
            };
    }

    private function parseFindingStatus(mixed $status): FindingStatus
    {
        if ($status instanceof FindingStatus) {
            return $status;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));

        return FindingStatus::tryFrom($normalized)
            ?? match ($normalized) {
                'in_progress', 'inprogress' => FindingStatus::InProgress,
                default => FindingStatus::from($normalized),
            };
    }

    private function parseFindingSeverity(mixed $severity): FindingSeverity
    {
        if ($severity instanceof FindingSeverity) {
            return $severity;
        }

        return FindingSeverity::from(strtolower((string) $severity));
    }

    private function parsePolicyStatus(mixed $status): PolicyStatus
    {
        if ($status instanceof PolicyStatus) {
            return $status;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));

        return PolicyStatus::tryFrom($normalized)
            ?? match ($normalized) {
                'review_due', 'reviewdue' => PolicyStatus::ReviewDue,
                default => PolicyStatus::from($normalized),
            };
    }

    private function parsePolicyCategory(mixed $category): PolicyCategory
    {
        if ($category instanceof PolicyCategory) {
            return $category;
        }

        return PolicyCategory::from(strtolower((string) $category));
    }
}
