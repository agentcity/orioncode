<?php

namespace App\Messenger\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AvitoTokenService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache // 🚀 Подключаем кэш (Redis)
    ) {}

    public function getAccessToken(string $clientId, string $clientSecret, string $accountId): ?string
    {
        // Ключ в Redis будет уникальным для каждого аккаунта
        $cacheKey = "avito_token_" . $accountId;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($clientId, $clientSecret) {
            try {
                $response = $this->httpClient->request('POST', 'https://api.avito.ru/token', [
                    'body' => [
                        'grant_type' => 'client_credentials',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                    ],
                ]);

                $data = $response->toArray();
                $token = $data['access_token'] ?? null;

                if ($token) {
                    // 🚀 Ставим время жизни (TTL) чуть меньше, чем дает Авито (обычно 1 день)
                    // Ставим 23 часа, чтобы точно не протух
                    $item->expiresAfter(82800);
                    return $token;
                }
            } catch (\Exception $e) {
                error_log("Avito Token API Error: " . $e->getMessage());
            }
            return null;
        });
    }
}
