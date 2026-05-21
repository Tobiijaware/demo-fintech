<?php

namespace App\Services\Swwipe;

use App\Exceptions\SwwipeException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SwwipeClient
{
    /**
     * @return array<string, mixed>
     */
    public function lookupNin(string $nin): array
    {
        $response = $this->get('/Lookup', ['nin' => $nin], 'NIN verification request failed.');
        $this->assertSuccess($response, 'NIN verification failed.');

        $data = $response->json('data') ?? [];

        return [
            'nin' => $data['nin'] ?? $nin,
            'firstname' => $data['firstname'] ?? null,
            'middlename' => $data['middlename'] ?? null,
            'lastname' => $data['surname'] ?? $data['lastname'] ?? null,
            'telephone' => $data['telephoneno'] ?? null,
            'email' => $data['email'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['residence_AddressLine1'] ?? null,
            'state' => $data['self_Origin_State'] ?? null,
            'lga' => $data['self_Origin_Lga'] ?? null,
            'photo' => $data['photo'] ?? null,
            'provider' => 'swwipe',
            'valid' => true,
            'message' => $response->json('message'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validateBvn(string $bvn, string $firstname, string $lastname): array
    {
        $response = $this->post('/Lookup/validate/bvn', [
            'bvn' => $bvn,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ], 'BVN verification request failed.');

        $this->assertSuccess($response, 'BVN verification failed.');

        $data = $response->json('data') ?? [];

        return [
            'bvn' => $data['bvn'] ?? $bvn,
            'firstname' => $data['firstname'] ?? null,
            'lastname' => $data['lastname'] ?? null,
            'provider' => 'swwipe',
            'valid' => true,
            'message' => $response->json('message'),
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    private function get(string $path, array $query = [], string $failureMessage = 'Request failed.'): Response
    {
        $response = Http::timeout(config('swwipe.timeout'))
            ->when(config('swwipe.api_key'), fn ($http) => $http->withToken(config('swwipe.api_key')))
            ->get($this->url($path), $query);

        if ($response->successful()) {
            return $response;
        }

        throw new SwwipeException($this->extractError($response, $failureMessage), $response->status(), $response->json());
    }

    /**
     * @param  array<string, string>  $body
     */
    private function post(string $path, array $body, string $failureMessage = 'Request failed.'): Response
    {
        $response = Http::timeout(config('swwipe.timeout'))
            ->acceptJson()
            ->when(config('swwipe.api_key'), fn ($http) => $http->withToken(config('swwipe.api_key')))
            ->post($this->url($path), $body);

        if ($response->successful()) {
            return $response;
        }

        throw new SwwipeException($this->extractError($response, $failureMessage), $response->status(), $response->json());
    }

    private function url(string $path): string
    {
        return rtrim(config('swwipe.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function assertSuccess(Response $response, string $fallback): void
    {
        $code = (string) ($response->json('statusCode') ?? '');
        if ($code !== '' && $code !== '00') {
            throw new SwwipeException(
                (string) ($response->json('message') ?? $fallback),
                422,
                $response->json(),
            );
        }
    }

    private function extractError(Response $response, string $fallback): string
    {
        $body = $response->json();

        return $body['message'] ?? $body['error'] ?? $fallback;
    }
}
