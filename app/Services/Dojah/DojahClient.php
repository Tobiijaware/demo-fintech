<?php

namespace App\Services\Dojah;

use App\Exceptions\DojahException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DojahClient
{
    /**
     * @return array<string, mixed>
     */
    public function lookupBvn(string $bvn): array
    {
        $response = $this->get('/api/v1/kyc/bvn/full', ['bvn' => $bvn], 'BVN verification request failed.');

        return $this->extractEntity($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupNin(string $nin): array
    {
        $response = $this->get('/api/v1/kyc/nin', ['nin' => $nin], 'NIN verification request failed.');

        return $this->extractEntity($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBanks(): array
    {
        $response = $this->get('/api/v1/general/banks', [], 'Failed to fetch banks.');

        return $this->extractEntityList($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        $response = $this->get('/api/v1/general/account', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ], 'Account resolution failed.');

        return $this->extractEntity($response);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query = [], string $failureMessage = 'Request failed.'): Response
    {
        $appId = config('dojah.app_id');
        $secretKey = config('dojah.secret_key');

        if (empty($appId) || empty($secretKey)) {
            throw new DojahException('Dojah credentials are not configured.', 500);
        }

        $response = Http::timeout(config('dojah.timeout'))
            ->withHeaders([
                'AppId' => $appId,
                'Authorization' => $secretKey,
            ])
            ->get(rtrim(config('dojah.base_url'), '/').$path, $query);

        if ($response->successful()) {
            return $response;
        }

        $body = $response->json();
        $message = $body['error']
            ?? $body['message']
            ?? data_get($body, 'entity.message')
            ?? $failureMessage;

        throw new DojahException($message, $response->status(), $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEntity(Response $response): array
    {
        $entity = $response->json('entity');

        if (! is_array($entity) || array_is_list($entity)) {
            throw new DojahException('Invalid response from identity provider.', 502, $response->json());
        }

        return $entity;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractEntityList(Response $response): array
    {
        $entity = $response->json('entity');

        if (! is_array($entity) || ! array_is_list($entity)) {
            throw new DojahException('Invalid response from identity provider.', 502, $response->json());
        }

        return $entity;
    }
}
