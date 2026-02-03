<?php

namespace App\Message;

class IncomingTelegramMessage
{
    private string $token;
    private array $payload;

    public function __construct(string $token, array $payload)
    {
        $this->token = $token;
        $this->payload = $payload;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
