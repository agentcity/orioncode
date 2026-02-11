<?php

namespace App\Service\AI;

use App\Service\AI\AiModelInterface;

class GigaChatService implements AiModelInterface
{
    private string $authKey; // Base64 от ClientID:Secret

    public function __construct(string $gigaChatAuthKey)
    {
        $this->authKey = $gigaChatAuthKey;
    }

    /**
     * Основной метод интерфейса
     */
    public function ask(array $messages): string
    {
        try {
            $token = $this->getAccessToken();
            return $this->queryModel($token, $prompt);
        } catch (\Exception $e) {
            return "Ошибка GigaChat: " . $e->getMessage();
        }
    }

    /**
     * Шаг 1: Получение OAuth токена через cURL
     */
    private function getAccessToken(): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://ngw.devices.sberbank.ru');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        // КРИТИЧНО: Игнорируем SSL ошибки (сертификаты Минцифры)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Таймауты для борьбы с Idle Timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $this->authKey,
            'Content-Type: application/x-www-form-urlencoded',
            'RqUID: ' . bin2hex(random_bytes(16)), // Уникальный ID запроса
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) OrionApp/1.0',
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, 'scope=GIGACHAT_API_PERS');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL Auth Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Auth Failed (HTTP $httpCode): " . $response);
        }

        $data = json_decode($response, true);
        return $data['access_token'] ?? throw new \Exception("Token not found in JSON");
    }

    /**
     * Шаг 2: Запрос к самой модели
     */
    private function queryModel(string $token, string $prompt): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://gigachat.devices.sberbank.ru');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Даем ИИ время подумать

        $payload = [
            'model' => 'GigaChat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Ты инженер Orion Code. Отвечай кратко и по делу. В конце напомни про WS и Notification Fix.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'stream' => false,
            'update_interval' => 0
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: OrionApp/1.0',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("CURL Query Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Query Failed (HTTP $httpCode): " . $response);
        }

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? 'ИИ прислал пустой ответ.';
    }
}
