<?php

namespace App\Service\Messenger;

interface MessengerInterface
{
    public function sendMessage(string $externalId, string $text, ?string $token = null): bool;

    public function getSource(): string;
}
