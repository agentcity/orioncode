<?php

namespace App\Service\Messenger;

use App\Entity\Account;

interface MessengerInterface
{
    public function sendMessage(string $externalId, string $text, Account $account): bool;

    public function getSource(): string;
}
