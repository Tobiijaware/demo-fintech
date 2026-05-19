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
        $response = $this->get('/api/v1/kyc/bvn/full', ['bvn' => $bvn]);

        return $this->extractEntity($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function lookupNin(string $nin): array
    {
        $response = $this->get('/api/v1/kyc/nin', ['nin' => $nin]);

        return $this->extractEntity($response);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query): Response
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
            ?? 'Identity verification request failed.';

        throw new DojahException($message, $response->status(), $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEntity(Response $response): array
    {
        $entity = $response->json('entity');

        if (! is_array($entity)) {
            throw new DojahException('Invalid response from identity provider.', 502, $response->json());
        }

        return $entity;
    }
}
