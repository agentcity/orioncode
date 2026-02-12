<?php

namespace App\Service\Messenger;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class VkMessenger implements MessengerInterface
{
    public function __construct(private HttpClientInterface $httpClient) {}

// backend/src/Service/Messenger/VkMessenger.php

// 1. Имя аргумента должно быть $externalId (как в интерфейсе)
// 2. Токен должен быть ?string и по умолчанию null
// 3. Возвращаемый тип должен быть bool
    public function sendMessage(string $externalId, string $text, ?string $token = null): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $this->httpClient->request('POST', 'https://api.vk.com', [
                'query' => [
                    'peer_id' => $externalId,
                    'message' => $text,
                    'access_token' => $token,
                    'v' => '5.131',
                    'random_id' => rand(0, 2147483647)
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("VK Send Error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Возвращаем строковый идентификатор мессенджера
     */
    public function getSource(): string
    {
        return 'vk';
    }
}
