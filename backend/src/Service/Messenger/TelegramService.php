<?php
namespace App\Service\Messenger;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService implements MessengerInterface {
    public function __construct(private HttpClientInterface $httpClient) {}

    public function sendMessage(string $externalId, string $text, ?string $token = null): bool {
        if (!$token) return false;
        try {
            $this->httpClient->request('POST', "https://api.telegram.org{$token}/sendMessage", [
                'json' => ['chat_id' => $externalId, 'text' => $text]
            ]);
            return true;
        } catch (\Exception $e) { return false; }
    }
    public function getSource(): string { return 'telegram'; }
}


