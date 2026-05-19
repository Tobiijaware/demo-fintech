<?php

namespace App\Services\Kyc;

use App\Enums\KycLevel;
use App\Enums\KycStatus;
use App\Exceptions\DojahException;
use App\Exceptions\RegistrationException;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\Dojah\DojahClient;
use Illuminate\Support\Facades\DB;

class IdentityVerificationService
{
    public function __construct(private DojahClient $dojahClient) {}

    /**
     * @return array<string, mixed>
     */
    public function validateBvn(User $user, string $bvn): array
    {
        $this->assertIdentifierAvailable('bvn', $bvn, $user);

        $entity = $this->dojahClient->lookupBvn($bvn);

        return $this->persistIdentityCheck($user, 'bvn', $bvn, $entity);
    }

    /**
     * @return array<string, mixed>
     */
    public function validateNin(User $user, string $nin): array
    {
        $this->assertIdentifierAvailable('nin', $nin, $user);

        $entity = $this->dojahClient->lookupNin($nin);

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
            $user->update([$column => $value]);

            $kyc = KycVerification::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'level' => KycLevel::IdentityVerification,
                ],
                [
                    'status' => KycStatus::Submitted,
                    'payload' => [
                        $column => $value,
                        'provider' => 'dojah',
                        'verified_at' => now()->toIso8601String(),
                        'entity' => $entity,
                    ],
                    'submitted_at' => now(),
                    'rejection_reason' => null,
                ]
            );

            return [
                'identifier_type' => $column,
                'identifier' => $value,
                'entity' => $entity,
                'kyc' => $kyc,
            ];
        });
    }
}
