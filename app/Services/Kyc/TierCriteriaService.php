<?php

namespace App\Services\Kyc;

use App\Enums\ApplicantType;
use App\Enums\OnboardingStatus;
use App\Models\OnboardingApplication;
use App\Models\TierCriterion;
use App\Models\TierDefinition;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TierCriteriaService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function allTierDefinitions(string $applicantType = 'customer'): array
    {
        $definitions = $this->loadDefinitions($applicantType);

        return $definitions
            ->map(fn (TierDefinition $definition) => $this->formatDefinition($definition))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function tierRequirements(string $applicantType, string $tier): array
    {
        $definition = $this->findDefinition($applicantType, $tier);

        if (! $definition) {
            throw new InvalidArgumentException('Unknown tier requirements.');
        }

        return $this->formatDefinition($definition);
    }

    /**
     * Signup-stage criteria for tier 1 (mobile registration).
     *
     * @return array<int, array<string, mixed>>
     */
    public function signupCriteria(string $applicantType = 'customer'): array
    {
        $definition = $this->findDefinition($applicantType, 'tier_1');
        if (! $definition) {
            return [];
        }

        return $definition->criteria
            ->where('group', 'signup')
            ->values()
            ->map(fn (TierCriterion $criterion) => $this->formatCriterion($criterion))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDefinition(TierDefinition $definition): array
    {
        $legacy = $definition->legacy_config ?? [];
        $criteria = $definition->criteria
            ->map(fn (TierCriterion $criterion) => $this->formatCriterion($criterion))
            ->values()
            ->all();

        $identity = collect($criteria)
            ->filter(fn (array $c) => str_starts_with($c['type'], 'identity_'))
            ->map(fn (array $c) => [
                'key' => str_replace('identity_', '', $c['type']),
                'label' => $c['label'],
            ])
            ->values()
            ->all();

        if ($identity === [] && ! empty($legacy['identity_any_of'])) {
            $identity = collect($legacy['identity_any_of'])->map(fn ($key) => [
                'key' => $key,
                'label' => match ($key) {
                    'bvn' => 'BVN verification',
                    'nin' => 'NIN verification',
                    default => $key,
                },
            ])->values()->all();
        }

        $documents = collect($criteria)
            ->filter(fn (array $c) => $c['type'] === 'document')
            ->map(fn (array $c) => [
                'key' => $c['config']['document_type'] ?? $c['key'],
                'label' => $c['label'],
            ])
            ->values()
            ->all();

        if ($documents === [] && ! empty($legacy['documents'])) {
            $documents = collect($legacy['documents'])->map(fn ($key) => [
                'key' => $key,
                'label' => config("onboarding.document_types.{$key}", $key),
            ])->values()->all();
        }

        return [
            'id' => $definition->id,
            'applicant_type' => $definition->applicant_type,
            'tier' => $definition->tier,
            'label' => $definition->label,
            'description' => $definition->description,
            'criteria' => $criteria,
            'identity' => $identity,
            'documents' => $documents,
            'limits' => $definition->limits ?? $this->defaultLimits($definition->applicant_type, $definition->tier),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCriterion(TierCriterion $criterion): array
    {
        return [
            'id' => $criterion->id,
            'key' => $criterion->key,
            'type' => $criterion->type,
            'label' => $criterion->label,
            'description' => $criterion->description,
            'required' => $criterion->required,
            'group' => $criterion->group,
            'rule_group' => $criterion->rule_group,
            'sort_order' => $criterion->sort_order,
            'config' => $criterion->config ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function completedForUser(User $user, OnboardingApplication $application, array $requirements): array
    {
        $uploaded = $application->documents()->pluck('document_type')->map(fn ($t) => $t->value)->all();

        $criteriaCompleted = [];
        foreach ($requirements['criteria'] ?? [] as $criterion) {
            $criteriaCompleted[$criterion['key']] = $this->isCriterionComplete($criterion, $user, $uploaded);
        }

        $identityKeys = collect($requirements['identity'] ?? [])->pluck('key')->all();
        $identityDone = false;
        foreach ($identityKeys as $key) {
            if (! empty($criteriaCompleted[$key]) || ($key === 'bvn' && ! empty($user->bvn)) || ($key === 'nin' && ! empty($user->nin))) {
                $identityDone = true;
                break;
            }
        }

        return [
            'identity' => $identityDone,
            'bvn' => ! empty($user->bvn),
            'nin' => ! empty($user->nin),
            'documents' => $uploaded,
            'criteria' => $criteriaCompleted,
        ];
    }

    /**
     * @param  array<string, mixed>  $requirements
     * @param  array<string, mixed>  $completed
     */
    public function isReadyToSubmit(array $requirements, array $completed): bool
    {
        $criteria = $requirements['criteria'] ?? [];
        $groups = collect($criteria)->groupBy('rule_group');

        foreach ($groups as $ruleGroup => $items) {
            if ($ruleGroup && str_ends_with((string) $ruleGroup, '_any')) {
                $requiredItems = collect($items)->where('required', true);
                if ($requiredItems->isEmpty()) {
                    continue;
                }
                $anyDone = $requiredItems->contains(
                    fn (array $item) => ! empty($completed['criteria'][$item['key'] ?? '']),
                );
                if (! $anyDone) {
                    return false;
                }
                continue;
            }

            foreach ($items as $item) {
                if (($item['required'] ?? false) && empty($completed['criteria'][$item['key'] ?? ''])) {
                    return false;
                }
            }
        }

        foreach ($requirements['documents'] ?? [] as $doc) {
            if (! in_array($doc['key'], $completed['documents'] ?? [], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Outstanding tier requirements for the user's current account tier (retroactive compliance).
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstandingObligations(User $user): array
    {
        $tier = $user->account_tier ?? 'tier_1';
        $definition = $this->findDefinition(ApplicantType::Customer->value, $tier);
        if (! $definition) {
            return [];
        }

        $requirements = $this->formatDefinition($definition);
        $application = $this->resolveComplianceApplication($user, $tier);
        $uploaded = $application
            ? $application->documents()->pluck('document_type')->map(fn ($t) => $t->value)->all()
            : [];

        $criteria = $requirements['criteria'] ?? [];
        $obligations = [];
        $seenGroups = [];

        foreach ($criteria as $criterion) {
            if (($criterion['group'] ?? '') === 'signup') {
                continue;
            }
            if (($criterion['required'] ?? false) === false) {
                continue;
            }
            if (! $this->isCriterionActive($criterion)) {
                continue;
            }

            $ruleGroup = $criterion['rule_group'] ?? null;
            if ($ruleGroup && str_ends_with((string) $ruleGroup, '_any')) {
                if (isset($seenGroups[$ruleGroup])) {
                    continue;
                }
                $seenGroups[$ruleGroup] = true;
                if ($this->isAnyGroupComplete($ruleGroup, $criteria, $this->completedSnapshot($user, $uploaded, $criteria))) {
                    continue;
                }
                $groupItems = collect($criteria)->where('rule_group', $ruleGroup)->values()->all();
                $obligations[] = $this->formatObligation([
                    ...$criterion,
                    'key' => $ruleGroup,
                    'type' => 'identity_group',
                    'label' => $this->identityGroupLabel($groupItems),
                    'description' => $this->groupDescription($groupItems),
                ]);

                continue;
            }

            if ($this->isCriterionComplete($criterion, $user, $uploaded)) {
                continue;
            }

            $obligations[] = $this->formatObligation($criterion);
        }

        return $obligations;
    }

    /**
     * @param  array<int, array<string, mixed>>  $obligations
     */
    public function resolveComplianceStatus(array $obligations): string
    {
        if ($obligations === []) {
            return 'compliant';
        }

        foreach ($obligations as $obligation) {
            if (! empty($obligation['overdue'])) {
                return 'overdue';
            }
        }

        return 'action_required';
    }

    /**
     * @param  array<string, mixed>  $criterion
     * @return array<string, mixed>
     */
    protected function formatObligation(array $criterion): array
    {
        $config = $criterion['config'] ?? [];
        $deadline = $config['deadline'] ?? null;
        $overdue = false;
        $daysRemaining = null;

        if (is_string($deadline) && $deadline !== '') {
            $deadlineTs = strtotime($deadline);
            $todayTs = strtotime('today');
            $overdue = $deadlineTs !== false && $deadlineTs < $todayTs;
            if ($deadlineTs !== false) {
                $daysRemaining = (int) ceil(($deadlineTs - $todayTs) / 86400);
            }
        }

        return [
            'key' => $criterion['key'],
            'type' => $criterion['type'],
            'label' => $criterion['label'],
            'description' => $criterion['description'] ?? null,
            'deadline' => $deadline,
            'overdue' => $overdue,
            'days_remaining' => $daysRemaining,
            'blocks_account' => (bool) ($config['blocks_account_after_deadline'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    protected function isCriterionActive(array $criterion): bool
    {
        $config = $criterion['config'] ?? [];
        $effectiveFrom = $config['effective_from'] ?? null;
        if (is_string($effectiveFrom) && $effectiveFrom !== '') {
            $ts = strtotime($effectiveFrom);
            if ($ts !== false && $ts > time()) {
                return false;
            }
        }

        return true;
    }

    protected function resolveComplianceApplication(User $user, string $tier): ?OnboardingApplication
    {
        return OnboardingApplication::query()
            ->where('user_id', $user->id)
            ->where('applicant_type', ApplicantType::Customer)
            ->where('tier', $tier)
            ->whereNotIn('status', [OnboardingStatus::Rejected])
            ->latest()
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     * @return array<string, mixed>
     */
    protected function completedSnapshot(User $user, array $uploadedDocuments, array $criteria): array
    {
        $criteriaCompleted = [];
        foreach ($criteria as $criterion) {
            $criteriaCompleted[$criterion['key']] = $this->isCriterionComplete($criterion, $user, $uploadedDocuments);
        }

        return [
            'identity' => collect($criteriaCompleted)->contains(true),
            'bvn' => ! empty($user->bvn),
            'nin' => ! empty($user->nin),
            'documents' => $uploadedDocuments,
            'criteria' => $criteriaCompleted,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groupItems
     */
    protected function identityGroupLabel(array $groupItems): string
    {
        if ($groupItems === []) {
            return 'Identity verification';
        }

        return collect($groupItems)->pluck('label')->filter()->implode(' or ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $groupItems
     */
    protected function groupDescription(array $groupItems): ?string
    {
        if (count($groupItems) === 1) {
            return $groupItems[0]['description'] ?? null;
        }

        $descriptions = collect($groupItems)
            ->pluck('description')
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values()
            ->all();

        return $descriptions === [] ? null : implode(' ', $descriptions);
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     */
    public function isAnyGroupComplete(string $ruleGroup, array $criteria, array $completed): bool
    {
        return collect($criteria)
            ->where('rule_group', $ruleGroup)
            ->contains(fn (array $criterion) => ! empty($completed['criteria'][$criterion['key'] ?? '']));
    }

    public function syncFromConfig(): void
    {
        $matrix = config('onboarding.tier_requirements', []);

        foreach ($matrix as $applicantType => $tiers) {
            $order = 0;
            foreach ($tiers as $tier => $config) {
                $order++;
                $definition = TierDefinition::query()->updateOrCreate(
                    [
                        'applicant_type' => $applicantType,
                        'tier' => $tier,
                    ],
                    [
                        'label' => $config['label'] ?? $tier,
                        'description' => $config['description'] ?? null,
                        'active' => true,
                        'sort_order' => $order,
                        'legacy_config' => [
                            'identity_any_of' => $config['identity_any_of'] ?? [],
                            'documents' => $config['documents'] ?? [],
                        ],
                        'limits' => $config['limits'] ?? null,
                    ],
                );

                $definition->criteria()->delete();

                foreach ($config['criteria'] ?? [] as $index => $criterion) {
                    $definition->criteria()->create([
                        'key' => $criterion['key'],
                        'type' => $criterion['type'],
                        'label' => $criterion['label'],
                        'description' => $criterion['description'] ?? null,
                        'required' => $criterion['required'] ?? true,
                        'group' => $criterion['group'] ?? 'kyc',
                        'rule_group' => $criterion['rule_group'] ?? null,
                        'sort_order' => $criterion['order'] ?? ($index + 1),
                        'config' => $criterion['config'] ?? null,
                    ]);
                }

                $this->ensureLegacyCriteria($definition, $config);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDefinition(TierDefinition $definition, array $data): TierDefinition
    {
        $definition->update([
            'label' => $data['label'] ?? $definition->label,
            'description' => $data['description'] ?? $definition->description,
            'active' => $data['active'] ?? $definition->active,
            'limits' => array_key_exists('limits', $data) ? $data['limits'] : $definition->limits,
        ]);

        return $definition->fresh(['criteria']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertCriterion(TierDefinition $definition, array $data, ?TierCriterion $existing = null): TierCriterion
    {
        $payload = [
            'key' => $data['key'],
            'type' => $data['type'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'required' => $data['required'] ?? true,
            'group' => $data['group'] ?? 'kyc',
            'rule_group' => $data['rule_group'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($definition->criteria()->count() + 1),
            'config' => $data['config'] ?? null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return $definition->criteria()->create($payload);
    }

    public function deleteCriterion(TierCriterion $criterion): void
    {
        $criterion->delete();
    }

    /**
     * @param  array<string, mixed>  $criterion
     */
    protected function isCriterionComplete(array $criterion, User $user, array $uploadedDocuments): bool
    {
        return match ($criterion['type']) {
            'text' => match ($criterion['key']) {
                'firstname' => ! empty($user->firstname),
                'lastname' => ! empty($user->lastname),
                default => ! empty(data_get($user, $criterion['key'])),
            },
            'email' => ! empty($user->email) && $user->email_verified_at !== null,
            'phone' => ! empty($user->phone),
            'date' => ! empty($user->dob),
            'identity_bvn' => ! empty($user->bvn),
            'identity_nin' => ! empty($user->nin),
            'document' => in_array(
                $criterion['config']['document_type'] ?? $criterion['key'],
                $uploadedDocuments,
                true,
            ),
            default => false,
        };
    }

    /**
     * @return array<string, float>
     */
    protected function defaultLimits(string $applicantType, string $tier): array
    {
        $limits = config("onboarding.tier_requirements.{$applicantType}.{$tier}.limits", []);

        return [
            'daily_transfer' => (float) ($limits['daily_transfer'] ?? 0),
            'single_transfer' => (float) ($limits['single_transfer'] ?? 0),
            'balance_limit' => (float) ($limits['balance_limit'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function ensureLegacyCriteria(TierDefinition $definition, array $config): void
    {
        if ($definition->criteria()->exists()) {
            return;
        }

        $order = 0;
        foreach ($config['identity_any_of'] ?? [] as $identityKey) {
            $order++;
            $definition->criteria()->create([
                'key' => $identityKey,
                'type' => $identityKey === 'bvn' ? 'identity_bvn' : 'identity_nin',
                'label' => $identityKey === 'bvn' ? 'BVN verification' : 'NIN verification',
                'description' => null,
                'required' => true,
                'group' => 'identity',
                'rule_group' => 'identity_any',
                'sort_order' => $order,
                'config' => null,
            ]);
        }

        foreach ($config['documents'] ?? [] as $documentKey) {
            $order++;
            $definition->criteria()->create([
                'key' => $documentKey,
                'type' => 'document',
                'label' => config("onboarding.document_types.{$documentKey}", $documentKey),
                'description' => null,
                'required' => true,
                'group' => 'documents',
                'rule_group' => null,
                'sort_order' => $order,
                'config' => ['document_type' => $documentKey],
            ]);
        }
    }

    protected function findDefinition(string $applicantType, string $tier): ?TierDefinition
    {
        $definition = TierDefinition::query()
            ->with('criteria')
            ->where('applicant_type', $applicantType)
            ->where('tier', $tier)
            ->where('active', true)
            ->first();

        if ($definition) {
            return $definition;
        }

        $this->syncFromConfig();

        return TierDefinition::query()
            ->with('criteria')
            ->where('applicant_type', $applicantType)
            ->where('tier', $tier)
            ->where('active', true)
            ->first();
    }

    /**
     * @return Collection<int, TierDefinition>
     */
    protected function loadDefinitions(string $applicantType): Collection
    {
        $definitions = TierDefinition::query()
            ->with('criteria')
            ->where('applicant_type', $applicantType)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        if ($definitions->isEmpty()) {
            $this->syncFromConfig();
            $definitions = TierDefinition::query()
                ->with('criteria')
                ->where('applicant_type', $applicantType)
                ->where('active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $definitions;
    }
}
