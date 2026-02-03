<?php

namespace App\Service\Messenger;

use GuzzleHttp\ClientInterface;
use App\Entity\Account;
use Psr\Log\LoggerInterface;

class TelegramClient
{
    private ClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(ClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    private function getBaseUrl(string $token): string
    {
        return "https://api.telegram.org/bot{$token}/";
    }

    public function sendMessage(Account $account, string $chatId, string $text): array
    {
        // Дешифруем токен из $account->getCredentials()['token']
        $token = $this->decryptToken($account->getCredentials()['token']); // Реализовать функцию дешифрования

        try {
            $response = $this->httpClient->post($this->getBaseUrl($token) . 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send Telegram message: ' . $e->getMessage());
            throw new \RuntimeException('Failed to send Telegram message.', 0, $e);
        }
    }

    public function setWebhook(Account $account, string $webhookUrl): array
    {
        $token = $this->decryptToken($account->getCredentials()['token']);

        try {
            $response = $this->httpClient->post($this->getBaseUrl($token) . 'setWebhook', [
                'json' => [
                    'url' => $webhookUrl,
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            $this->logger->error('Failed to set Telegram webhook: ' . $e->getMessage());
            throw new \RuntimeException('Failed to set Telegram webhook.', 0, $e);
        }
    }

    private function decryptToken(string $encryptedToken): string
    {
        // TODO: Реализовать логику дешифрования токена.
        // Используйте Symfony's `Sodium` component или другой надежный метод.
        return $encryptedToken; // Заглушка
    }
}
