<?php

namespace App\Services\Registration;

use App\Exceptions\RegistrationException;
use App\Exceptions\SwwipeException;
use App\Models\User;
use App\Services\Swwipe\SwwipeClient;
use Illuminate\Support\Facades\Cache;

class RegistrationProfileService
{
    private const CACHE_PREFIX = 'registration_profile:';

    public function __construct(
        private EmailVerificationService $emailVerificationService,
        private SwwipeClient $swwipeClient,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function saveProfile(string $email, array $data): array
    {
        $this->emailVerificationService->assertEmailVerifiedForRegistration($email);

        $profile = [
            'firstname' => trim((string) ($data['firstname'] ?? '')),
            'lastname' => trim((string) ($data['lastname'] ?? '')),
            'phone' => preg_replace('/\D/', '', (string) ($data['phone'] ?? '')),
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'bvn_verified' => false,
        ];

        if ($profile['firstname'] === '' || $profile['lastname'] === '') {
            throw new RegistrationException('First name and last name are required.', 422);
        }

        if (strlen($profile['phone']) < 10) {
            throw new RegistrationException('Enter a valid phone number.', 422);
        }

        if (empty($profile['date_of_birth'])) {
            throw new RegistrationException('Date of birth is required.', 422);
        }

        $existing = $this->getProfile($email) ?? [];
        $merged = array_merge($existing, $profile);
        $this->store($email, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateBvn(string $email, string $bvn): array
    {
        $this->emailVerificationService->assertEmailVerifiedForRegistration($email);
        $profile = $this->getProfile($email);

        if (! $profile || empty($profile['firstname']) || empty($profile['lastname'])) {
            throw new RegistrationException('Complete your profile before verifying BVN.', 422);
        }

        $bvn = preg_replace('/\D/', '', $bvn) ?? '';
        if (strlen($bvn) !== 11) {
            throw new RegistrationException('BVN must be 11 digits.', 422);
        }

        if (User::query()->where('bvn', $bvn)->exists()) {
            throw new RegistrationException('BVN is already linked to another account.', 409);
        }

        try {
            $entity = $this->swwipeClient->validateBvn(
                $bvn,
                $profile['firstname'],
                $profile['lastname'],
            );
        } catch (SwwipeException $e) {
            throw new RegistrationException($e->getMessage(), $e->statusCode);
        }

        $resolvedName = trim(implode(' ', array_filter([
            $entity['firstname'] ?? $profile['firstname'],
            $entity['middlename'] ?? null,
            $entity['lastname'] ?? $profile['lastname'],
        ])));

        $profile['bvn'] = $bvn;
        $profile['bvn_verified'] = true;
        $profile['resolved_name'] = $resolvedName !== '' ? strtoupper($resolvedName) : null;
        $profile['bvn_entity'] = $entity;
        $this->store($email, $profile);

        return [
            'identifier_type' => 'bvn',
            'identifier' => $bvn,
            'resolved_name' => $profile['resolved_name'],
            'entity' => $entity,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assertReadyForCompletion(string $email): array
    {
        $profile = $this->getProfile($email);
        if (! $profile) {
            throw new RegistrationException('Complete your profile before creating an account.', 422);
        }

        if (empty($profile['bvn_verified']) || empty($profile['bvn'])) {
            throw new RegistrationException('Verify your BVN before creating an account.', 422);
        }

        return $profile;
    }

    public function forget(string $email): void
    {
        Cache::forget($this->cacheKey($email));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProfile(string $email): ?array
    {
        $value = Cache::get($this->cacheKey($email));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function store(string $email, array $profile): void
    {
        Cache::put(
            $this->cacheKey($email),
            $profile,
            now()->addMinutes((int) config('registration.complete_within_minutes', 60)),
        );
    }

    protected function cacheKey(string $email): string
    {
        return self::CACHE_PREFIX.strtolower(trim($email));
    }
}
