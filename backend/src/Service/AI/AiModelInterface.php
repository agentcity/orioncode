<?php

namespace App\Service\AI;

interface AiModelInterface
{
    public function ask(string $prompt): string;
}
