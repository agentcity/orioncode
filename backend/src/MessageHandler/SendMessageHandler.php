<?php

namespace App\MessageHandler;

use App\Message\SendMessage;
use App\Repository\MessageRepository;
use Predis\ClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class SendMessageHandler
{
    public function __construct(
        private MessageRepository $repository,
        private ClientInterface $redisClient,
        private HttpClientInterface $httpClient,
        private string $tgToken
    ) {}

    public function __invoke(SendMessage $command)
    {
        $message = $this->repository->find($command->getMessageId());
        if (!$message || $message->getDirection() !== 'outgoing') return;

        $conversation = $message->getConversation();
        $assignedUser = $conversation->getAssignedTo();

        // 1. Отправка в Telegram
        if ($conversation->getType() === 'telegram') {
            try {
                $this->sendToTelegram($conversation->getExternalId(), $message);
                $message->setStatus('delivered');
            } catch (\Exception $e) {
                $message->setStatus('failed');
            }
        }

        // 2. Публикуем в Redis
        // ВАЖНО: Убираем 'event' => 'statusUpdate', чтобы фронтенд воспринял это как newMessage
        $this->redisClient->publish('new_message_channel', json_encode([
            'id' => $message->getId()->toString(),
            'text' => $message->getText(),
            'conversationId' => $conversation->getId()->toString(),
            'assignedToId' => $assignedUser ? $assignedUser->getId()->toString() : null,
            'direction' => $message->getDirection(),
            'status' => $message->getStatus(),
            'type' => $conversation->getType(),
            'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
        ]));
    }

    private function sendToTelegram(string $chatId, $message): void
    {
        // Исправлен путь /bot
        $url = "https://api.telegram.org{$this->tgToken}/sendMessage";

        $this->httpClient->request('POST', $url, [
            'json' => [
                'chat_id' => $chatId,
                'text' => $message->getText(),
            ]
        ]);
    }
}
