<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiService implements AiModelInterface
{
    private $httpClient;
    private $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $openAiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $openAiKey;
    }

    public function ask(array $messages): string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini', // Самая сбалансированная модель
                    'messages' => [
                        ['role' => 'system', 'content' => 'Ты инженер Orion Code. Отвечай кратко.'],
                        $messages
                    ],
                    'max_tokens' => 500
                ],
                'timeout' => 30
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'ChatGPT промолчал...';

        } catch (\Exception $e) {
            // Если Jino заблокирован, здесь вылетит ошибка соединения
            return "Ошибка OpenAI: " . $e->getMessage();
        }
    }
}
