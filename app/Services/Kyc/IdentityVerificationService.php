<?php

namespace App\Services\Kyc;

use App\Enums\KycLevel;
use App\Enums\KycStatus;
use App\Exceptions\RegistrationException;
use App\Exceptions\SwwipeException;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Swwipe\SwwipeClient;
use Illuminate\Support\Facades\DB;

class IdentityVerificationService
{
    public function __construct(private SwwipeClient $swwipeClient) {}

    /**
     * @return array<string, mixed>
     */
    public function validateBvn(User $user, string $bvn, string $firstname, string $lastname): array
    {
        $this->assertIdentifierAvailable('bvn', $bvn, $user);

        try {
            $entity = $this->swwipeClient->validateBvn($bvn, $firstname, $lastname);
        } catch (SwwipeException $e) {
            throw new RegistrationException($e->getMessage(), $e->statusCode);
        }

        return $this->persistIdentityCheck($user, 'bvn', $bvn, $entity);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateNin(User $user, string $nin): array
    {
        $this->assertIdentifierAvailable('nin', $nin, $user);

        try {
            $entity = $this->swwipeClient->lookupNin($nin);
        } catch (SwwipeException $e) {
            throw new RegistrationException($e->getMessage(), $e->statusCode);
        }

        return $this->persistIdentityCheck($user, 'nin', $nin, $entity);
    }

    private function assertIdentifierAvailable(string $column, string $value, User $user): void
    {
        $exists = User::query()
            ->where($column, $value)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            throw new RegistrationException(
                strtoupper($column).' is already linked to another account.',
                409
            );
        }
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array<string, mixed>
     */
    private function persistIdentityCheck(User $user, string $column, string $value, array $entity): array
    {
        return DB::transaction(function () use ($user, $column, $value, $entity) {
            $updates = [$column => $value];
            if (! empty($entity['firstname']) && empty($user->firstname)) {
                $updates['firstname'] = $entity['firstname'];
            }
            if (! empty($entity['lastname']) && empty($user->lastname)) {
                $updates['lastname'] = $entity['lastname'];
            }
            $user->update($updates);

            $kyc = KycVerification::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'level' => KycLevel::IdentityVerification,
                ],
                [
                    'status' => KycStatus::Submitted,
                    'payload' => [
                        $column => $value,
                        'provider' => 'swwipe',
                        'verified_at' => now()->toIso8601String(),
                        'entity' => $entity,
                    ],
                    'submitted_at' => now(),
                    'rejection_reason' => null,
                ]
            );

            $resolvedName = trim(implode(' ', array_filter([
                $entity['firstname'] ?? $user->firstname,
                $entity['middlename'] ?? null,
                $entity['lastname'] ?? $user->lastname,
            ])));

            return [
                'identifier_type' => $column,
                'identifier' => $value,
                'entity' => $entity,
                'resolved_name' => $resolvedName !== '' ? strtoupper($resolvedName) : null,
                'kyc' => $kyc,
            ];
        });
    }
}
