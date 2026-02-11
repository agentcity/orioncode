<?php

namespace App\Service\AI;

interface AiModelInterface
{
    /** @param array $history Массив сообщений [['role' => 'user', 'content' => '...'], ...] */
    public function ask(array $history): string;
}
