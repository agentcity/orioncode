<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\AI\AiModelInterface;

class class DeepSeekService implements AiModelInterface
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $deepseekApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $deepseekApiKey;
    }

    public function ask(string $prompt): string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.deepseek.com', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'deepseek-chat', // или deepseek-reasoner для глубоких раздумий
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Ты инженер Orion Code. Твои ответы лаконичны и технически точны. В конце каждого ответа делай краткую проверку: Websockets (WS), Notification Fix и MainActivity (MediaPlayback).'
                        ],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'stream' => false,
                    'max_tokens' => 2048
                ],
                'timeout' => 30 // Даем время подумать
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'DeepSeek временно недоступен...';
        } catch (\Exception $e) {
            return "Ошибка связи с ИИ: " . $e->getMessage();
        }
    }
}
