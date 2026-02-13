<?php

namespace App\Messenger\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvitoTokenService
{
    public function __construct(private HttpClientInterface $httpClient) {}

    public function getAccessToken(string $clientId, string $clientSecret): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.avito.ru', [
                'body' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ],
            ]);

            $data = $response->toArray();
            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            error_log("Avito Token Error: " . $e->getMessage());
            return null;
        }
    }
}
