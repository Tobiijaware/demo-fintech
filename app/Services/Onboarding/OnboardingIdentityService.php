<?php

namespace App\Services\Onboarding;

use App\Exceptions\SwwipeException;
use App\Services\Swwipe\SwwipeClient;
use InvalidArgumentException;

class OnboardingIdentityService
{
    public function __construct(private SwwipeClient $swwipeClient) {}

    /**
     * @return array<string, mixed>
     */
    public function verifyBvn(string $bvn, string $firstname, string $lastname): array
    {
        $bvn = preg_replace('/\D/', '', $bvn) ?? '';

        if (strlen($bvn) !== 11) {
            throw new InvalidArgumentException('BVN must be 11 digits.');
        }

        try {
            $entity = $this->swwipeClient->validateBvn($bvn, $firstname, $lastname);
        } catch (SwwipeException $e) {
            return [
                'valid' => false,
                'identifier_type' => 'bvn',
                'identifier' => $this->mask($bvn),
                'message' => $e->getMessage(),
                'provider' => 'swwipe',
            ];
        }

        $resolvedName = trim(implode(' ', array_filter([
            $entity['firstname'] ?? null,
            $entity['lastname'] ?? null,
        ])));

        return [
            'valid' => true,
            'identifier_type' => 'bvn',
            'identifier' => $this->mask($bvn),
            'resolved_name' => $resolvedName !== '' ? strtoupper($resolvedName) : null,
            'entity' => $entity,
            'provider' => 'swwipe',
            'verified_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyNin(string $nin): array
    {
        $nin = preg_replace('/\D/', '', $nin) ?? '';

        if (strlen($nin) !== 11) {
            throw new InvalidArgumentException('NIN must be 11 digits.');
        }

        try {
            $entity = $this->swwipeClient->lookupNin($nin);
        } catch (SwwipeException $e) {
            return [
                'valid' => false,
                'identifier_type' => 'nin',
                'identifier' => $this->mask($nin),
                'message' => $e->getMessage(),
                'provider' => 'swwipe',
            ];
        }

        $resolvedName = trim(implode(' ', array_filter([
            $entity['firstname'] ?? null,
            $entity['middlename'] ?? null,
            $entity['lastname'] ?? null,
        ])));

        return [
            'valid' => true,
            'identifier_type' => 'nin',
            'identifier' => $this->mask($nin),
            'resolved_name' => $resolvedName !== '' ? strtoupper($resolvedName) : null,
            'entity' => $entity,
            'provider' => 'swwipe',
            'verified_at' => now()->toIso8601String(),
        ];
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).substr($value, -4);
    }
}
