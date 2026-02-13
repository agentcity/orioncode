<?php

namespace App\Messenger\Service;

class AvitoMessenger implements MessengerInterface
{
    public function sendMessage(string $externalId, string $text, ?string $token = null): bool
    {
        // Авито требует POST запрос на /messenger/v1/accounts/{user_id}/chats/{chat_id}/messages
        // Где externalId у нас будет составным (user_id + chat_id)
        $response = $this->httpClient->request('POST', "https://api.avito.ru/messenger/v1/accounts/{$userId}/chats/{$chatId}/messages", [
            'headers' => ['Authorization' => "Bearer {$token}"],
            'json' => [
                'message' => ['text' => $text],
                'type' => 'text'
            ]
        ]);
        return $response->getStatusCode() === 200;
    }

    public function getSource(): string { return 'avito'; }
}
