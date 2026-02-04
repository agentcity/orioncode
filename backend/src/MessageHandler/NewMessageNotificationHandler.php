<?php

namespace App\MessageHandler;

use App\Message\NewMessageNotification;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Predis\ClientInterface;

#[AsMessageHandler]
class NewMessageNotificationHandler
{
    private ClientInterface $redis; // Или другой клиент Redis

    public function __construct(ClientInterface $redis) // Или через DI с Redis client
    {
        $this->redis = $redis;
    }

    public function __invoke(NewMessageNotification $notification)
    {
        $this->redis->publish('new_message_channel', json_encode($notification->getData()));
    }
}
