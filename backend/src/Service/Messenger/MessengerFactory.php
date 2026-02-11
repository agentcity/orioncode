<?php

namespace App\Service\Messenger;

class MessengerFactory
{
    private $messengers;

    public function __construct(iterable $messengers)
    {
        $this->messengers = $messengers;
    }

    public function get(string $source): ?MessengerInterface
    {
        foreach ($this->messengers as $messenger) {
            if ($messenger->getSource() === $source) return $messenger;
        }
        return null;
    }
}
