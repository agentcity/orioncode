<?php
namespace App\MessageHandler;

use App\Message\NewMessageNotification;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Redis; // Или Predis/PhpRedis

#[AsMessageHandler]
class NewMessageNotificationHandler
{
    private Redis $redis; // Или другой клиент Redis

    public function __construct(Redis $redis) // Или через DI с Redis client
    {
        $this->redis = $redis;
    }

    public function __invoke(NewMessageNotification $notification)
    {
        $this->redis->publish('new_message_channel', json_encode($notification->getData()));
    }
}
