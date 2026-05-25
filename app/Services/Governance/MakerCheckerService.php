<?php

namespace App\Services\Governance;

use App\Models\MakerCheckerPolicy;
use App\Models\User;
use InvalidArgumentException;

class MakerCheckerService
{
    /** @var \Illuminate\Support\Collection<int, MakerCheckerPolicy>|null */
    private $policies = null;

    public function findPolicyForResource(string $resource): ?MakerCheckerPolicy
    {
        return $this->enforcedPolicies()
            ->first(fn (MakerCheckerPolicy $policy) => $policy->resource === $resource);
    }

    public function canActAsMaker(User $user, MakerCheckerPolicy $policy): bool
    {
        $slug = $this->roleSlug($user);
        if (! $slug) {
            return false;
        }

        foreach ($policy->role_pairs ?? [] as $pair) {
            if (in_array($slug, $pair['maker_roles'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public function canActAsChecker(User $user, MakerCheckerPolicy $policy, ?User $maker = null): bool
    {
        $checkerSlug = $this->roleSlug($user);
        if (! $checkerSlug) {
            return false;
        }

        if ($maker === null) {
            foreach ($policy->role_pairs ?? [] as $pair) {
                if (in_array($checkerSlug, $pair['checker_roles'] ?? [], true)) {
                    return true;
                }
            }

            return false;
        }

        $makerSlug = $this->roleSlug($maker);
        if (! $makerSlug) {
            return false;
        }

        return in_array($checkerSlug, $this->resolveCheckerRoles($policy, $makerSlug), true);
    }

    public function assertMaker(User $user, string $resource): MakerCheckerPolicy
    {
        $policy = $this->findPolicyForResource($resource);
        if (! $policy) {
            throw new InvalidArgumentException("No enforced maker-checker policy for resource [{$resource}].");
        }

        if (! $this->canActAsMaker($user, $policy)) {
            throw new InvalidArgumentException('Your role is not permitted to initiate this action under maker-checker policy.');
        }

        return $policy;
    }

    public function assertChecker(User $user, MakerCheckerPolicy $policy, User $maker): void
    {
        if ((int) $user->id === (int) $maker->id) {
            throw new InvalidArgumentException('Maker-checker rule: you cannot approve an action you initiated.');
        }

        if (! $this->canActAsChecker($user, $policy, $maker)) {
            throw new InvalidArgumentException('Your role is not permitted to approve this action under maker-checker policy.');
        }
    }

    /**
     * @return list<string>
     */
    public function resolveCheckerRoles(MakerCheckerPolicy $policy, string $makerRoleSlug): array
    {
        $roles = [];

        foreach ($policy->role_pairs ?? [] as $pair) {
            if (in_array($makerRoleSlug, $pair['maker_roles'] ?? [], true)) {
                foreach ($pair['checker_roles'] ?? [] as $checkerRole) {
                    $roles[$checkerRole] = true;
                }
            }
        }

        return array_keys($roles);
    }

    /**
     * @return \Illuminate\Support\Collection<int, MakerCheckerPolicy>
     */
    private function enforcedPolicies()
    {
        if ($this->policies === null) {
            $this->policies = MakerCheckerPolicy::query()
                ->where('enforced', true)
                ->orderBy('sort_order')
                ->get();
        }

        return $this->policies;
    }

    private function roleSlug(User $user): ?string
    {
        $user->loadMissing('backofficeRole');

        return $user->backofficeRole?->slug
            ?? ($user->role?->value === 'admin' ? 'super_admin' : null);
    }
}
