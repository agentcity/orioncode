<?php
namespace App\Service\Messenger;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\Account;

class TelegramService implements MessengerInterface {
    public function __construct(private HttpClientInterface $httpClient) {}

    public function sendMessage(string $externalId, string $text,  Account $account): bool {
        $token = $account->getCredential('telegram_token');
        if (!$token) return false;
        try {
            $this->httpClient->request('POST', "https://api.telegram.org/bot{$token}/sendMessage", [
                'json' => ['chat_id' => $externalId, 'text' => $text]
            ]);
            return true;
        } catch (\Exception $e) { return false; }
    }
    public function getSource(): string { return 'telegram'; }
}


