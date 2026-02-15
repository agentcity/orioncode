<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\HttpClient;

class AvitoProductionWebhookTest extends WebTestCase
{
    public function testLiveAvitoWebhookOnProduction(): void
    {
        // 🚀 РЕАЛЬНЫЙ URL ТВОЕГО ВЕБХУКА НА JINO
        $url = 'https://api.orioncode.ru/api/webhooks/avito/69bb5ac5-bab7-4cee-a4cc-9fd69d318aeb';

        $client = HttpClient::create();

        // 1. Имитируем структуру JSON, которую ждет твой AvitoController
        $payload = [
            'payload' => [
                'value' => [
                    'id' => 'test-prod-msg-' . time(),
                    'chat_id' => 'test-prod-chat',
                    'user_id' => '12345678', // Реальный или тестовый ID юзера Авито
                    'author_id' => '12345678',
                    'text' => '🔥 Тестовый лид из автоматического теста Orion 2026'
                ]
            ]
        ];

        // 2. Делаем РЕАЛЬНЫЙ POST-запрос на прод
        $response = $client->request('POST', $url, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Avito-Webhook-Test/1.0'
            ]
        ]);

        // 3. ПРОВЕРЯЕМ СТАТУС (Должен быть 200 OK)
        $statusCode = $response->getStatusCode();

        $this->assertEquals(200, $statusCode, "Прод вернул ошибку $statusCode. Проверь логи на Jino!");

        $content = $response->getContent();
        $this->assertStringContainsString('ok', $content, 'Прод не ответил "ok"');
    }
}
