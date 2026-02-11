<?php

namespace App\Service\AI;

use App\Service\AI\AiModelInterface;

class YandexGptService implements AiModelInterface
{
    private string $apiKey;
    private string $folderId;

    public function __construct(string $yandexApiKey, string $yandexFolderId)
    {
        $this->apiKey = $yandexApiKey;
        $this->folderId = $yandexFolderId;
    }

    public function ask(array $messages): string
    {
        $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

        $payload = [
            'modelUri' => "gpt://{$this->folderId}/yandexgpt-lite/latest",
            'completionOptions' => [
                'stream' => false,
                'temperature' => 0.6,
                'maxTokens' => "1000"
            ],
            'messages' => [
                ['role' => 'system', 'text' => 'Ты инженер Orion Code. Отвечай кратко.'],
                $messages
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // На всякий случай для Jino
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Api-Key ' . $this->apiKey,
            'x-folder-id: ' . $this->folderId,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return "Ошибка cURL: " . $error;
        }

        if ($httpCode !== 200) {
            return "Ошибка Яндекс (HTTP $httpCode): " . $response;
        }

        $data = json_decode($response, true);
        return $data['result']['alternatives'][0]['message']['text'] ?? 'Яндекс прислал пустой ответ.';
    }
}
