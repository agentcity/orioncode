<?php

namespace App\Message;

class NewMessageNotification
{
    public function __construct(private array $data) {}
    public function getData(): array { return $this->data; }
}
