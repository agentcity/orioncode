<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceService implements AiModelInterface
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $huggingFaceKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $huggingFaceKey;
    }

    public function ask(array $messages): string
    {

    }
}
