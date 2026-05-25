<?php

namespace App\Services\Governance;

use App\Enums\ProvisioningRequestStatus;
use App\Enums\ProvisioningRequestType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\BackofficeRole;
use App\Models\ProvisioningRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProvisioningRequestService
{
    public function __construct(private AuditLogService $auditLog) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPending(array $filters = []): LengthAwarePaginator
    {
        $query = ProvisioningRequest::query()
            ->with(['requestedBy.backofficeRole', 'checker'])
            ->where('status', ProvisioningRequestStatus::Pending)
            ->orderByDesc('created_at');

        if (! empty($filters['type'])) {
            $type = ProvisioningRequestType::tryFrom($filters['type']);
            if ($type) {
                $query->where('type', $type);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = ProvisioningRequest::query()
            ->with(['requestedBy.backofficeRole', 'checker'])
            ->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $status = ProvisioningRequestStatus::tryFrom($filters['status']);
            if ($status) {
                $query->where('status', $status);
            }
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function findByReference(string $reference): ProvisioningRequest
    {
        return ProvisioningRequest::query()
            ->with(['requestedBy.backofficeRole', 'checker'])
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function approve(ProvisioningRequest $request, User $checker): ProvisioningRequest
    {
        $this->assertPending($request);
        $this->assertSuperAdminChecker($checker, $request);

        return DB::transaction(function () use ($request, $checker) {
            $createdUser = null;

            if ($request->type === ProvisioningRequestType::NewUser) {
                $createdUser = $this->provisionNewUser($request->subject);
            } elseif ($request->type === ProvisioningRequestType::RoleChange) {
                $this->applyRoleChange($request->subject);
            }

            $request->update([
                'status' => ProvisioningRequestStatus::Approved,
                'checker_id' => $checker->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLog->record(
                $checker,
                'provisioning.approved',
                'ProvisioningRequest',
                $request->reference,
                "Approved provisioning request {$request->reference}",
                [
                    'type' => $request->type->value,
                    'created_user_id' => $createdUser?->id,
                ],
            );

            return $request->fresh(['requestedBy.backofficeRole', 'checker']);
        });
    }

    public function reject(ProvisioningRequest $request, User $checker, ?string $notes = null): ProvisioningRequest
    {
        $this->assertPending($request);
        $this->assertSuperAdminChecker($checker, $request);

        $request->update([
            'status' => ProvisioningRequestStatus::Rejected,
            'checker_id' => $checker->id,
            'notes' => $notes ?? $request->notes,
            'reviewed_at' => now(),
        ]);

        $this->auditLog->record(
            $checker,
            'provisioning.rejected',
            'ProvisioningRequest',
            $request->reference,
            "Rejected provisioning request {$request->reference}",
            ['notes' => $notes],
        );

        return $request->fresh(['requestedBy.backofficeRole', 'checker']);
    }

    public function returnForClarification(ProvisioningRequest $request, User $checker, string $notes): ProvisioningRequest
    {
        $this->assertPending($request);
        $this->assertSuperAdminChecker($checker, $request);

        $request->update([
            'status' => ProvisioningRequestStatus::Returned,
            'checker_id' => $checker->id,
            'notes' => $notes,
            'reviewed_at' => now(),
        ]);

        $this->auditLog->record(
            $checker,
            'provisioning.returned',
            'ProvisioningRequest',
            $request->reference,
            "Returned provisioning request {$request->reference} for clarification",
            ['notes' => $notes],
        );

        return $request->fresh(['requestedBy.backofficeRole', 'checker']);
    }

    public static function generateReference(): string
    {
        $latest = ProvisioningRequest::query()
            ->where('reference', 'like', 'PROV-%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if ($latest && preg_match('/PROV-(\d+)/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return 'PROV-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function assertPending(ProvisioningRequest $request): void
    {
        if ($request->status !== ProvisioningRequestStatus::Pending) {
            throw new InvalidArgumentException('Only pending provisioning requests can be reviewed.');
        }
    }

    private function assertSuperAdminChecker(User $checker, ProvisioningRequest $request): void
    {
        $checker->loadMissing('backofficeRole');

        if ($checker->id === $request->requested_by_id) {
            throw new InvalidArgumentException('The approver must be different from the requester.');
        }

        $slug = $checker->backofficeRole?->slug;
        if ($slug !== 'super_admin') {
            throw new InvalidArgumentException('Only a super admin can approve provisioning requests.');
        }
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function provisionNewUser(array $subject): User
    {
        $userDetails = $subject['user_details'] ?? $subject['userDetails'] ?? [];
        $email = $userDetails['email'] ?? null;

        if (! $email) {
            throw new InvalidArgumentException('Subject must include user email.');
        }

        $roleSlug = $subject['role_slug'] ?? $this->resolveRoleSlug($subject['role'] ?? null);
        $role = BackofficeRole::query()->where('slug', $roleSlug)->firstOrFail();

        $name = $userDetails['name'] ?? '';
        [$firstname, $lastname] = $this->splitName($name);

        return User::query()->create([
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => Hash::make(Str::password(12)),
            'user_type' => UserType::Staff,
            'backoffice_role_id' => $role->id,
            'job_title' => $subject['role'] ?? $role->name,
            'hub' => $subject['region'] ?? null,
            'role' => 'admin',
            'status' => UserStatus::Approved,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function applyRoleChange(array $subject): void
    {
        $userDetails = $subject['user_details'] ?? $subject['userDetails'] ?? [];
        $email = $userDetails['email'] ?? null;

        if (! $email) {
            throw new InvalidArgumentException('Subject must include user email.');
        }

        $user = User::query()->where('email', $email)->firstOrFail();
        $roleSlug = $subject['role_slug'] ?? $this->resolveRoleSlug($subject['role'] ?? null);
        $role = BackofficeRole::query()->where('slug', $roleSlug)->firstOrFail();

        $user->update([
            'backoffice_role_id' => $role->id,
            'job_title' => $subject['role'] ?? $role->name,
            'hub' => $subject['region'] ?? $user->hub,
        ]);
    }

    private function resolveRoleSlug(?string $roleLabel): string
    {
        $map = [
            'kyc officer' => 'kyc_officer',
            'settlement sr' => 'settlement_officer',
            'settlement officer' => 'settlement_officer',
            'audit viewer' => 'operations_lead',
        ];

        $normalized = Str::lower(trim($roleLabel ?? ''));

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        $slug = Str::slug($roleLabel ?? '', '_');

        if (BackofficeRole::query()->where('slug', $slug)->exists()) {
            return $slug;
        }

        throw new InvalidArgumentException("Unable to resolve role: {$roleLabel}");
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return ['Staff', 'User'];
        }

        if (count($parts) === 1) {
            return [$parts[0], 'User'];
        }

        $firstname = array_shift($parts);
        $lastname = implode(' ', $parts);

        return [$firstname, $lastname];
    }
}
