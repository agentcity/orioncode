<?php


namespace App\Messenger\Service;

use App\Entity\Account;
use App\Messenger\Service\AvitoTokenService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AvitoMessenger implements MessengerInterface
{
    public function __construct(
        private AvitoTokenService $tokenService,
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * Отправка сообщения в чат Авито
     */
    public function sendMessage(string $externalId, string $text, Account $account): bool
    {
        $creds = $account->getCredentials();

        // 🚀 1. Получаем динамический токен (с кэшем в Redis на 23 часа)
        $accessToken = $this->tokenService->getAccessToken(
            $creds['client_id'] ?? '',
            $creds['client_secret'] ?? '',
            $account->getId()->toString()
        );

        if (!$accessToken) {
            error_log("AVITO AUTH ERROR: Could not get token for account " . $account->getId());
            return false;
        }

        // 🚀 2. Подготовка параметров отправки
        // Для Авито URL требует ID пользователя (из credentials) и ID чата (externalId)
        $userId = $creds['user_id'] ?? '';
        $chatId = $externalId;

        if (empty($userId) || empty($chatId)) {
            error_log("AVITO SEND ERROR: Missing userId or chatId for account " . $account->getId());
            return false;
        }

        try {
            $url = "https://api.avito.ru/messenger/v1/accounts/{$userId}/chats/{$chatId}/messages";

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'message' => [
                        'text' => $text
                    ],
                    'type' => 'text'
                ]
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                error_log("AVITO API ERROR: Status {$statusCode}, Body: " . $response->getContent(false));
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("AVITO CRITICAL ERROR: " . $e->getMessage());
            return false;
        }
    }

    public function getSource(): string
    {
        return 'avito';
    }
}
