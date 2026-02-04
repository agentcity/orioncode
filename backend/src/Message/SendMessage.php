<?php

namespace App\Message;

class SendMessage
{
    public function __construct(
        private string $messageId // Передаем только ID, сущность достанем из БД в хендлере
    ) {}

    public function getMessageId(): string { return $this->messageId; }
}
