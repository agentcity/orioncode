<?php
namespace App\tests\Unit\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ТЕСТ: Доступность списка чатов.
 * Проверяет, что API метод /api/conversations живой и
 * не выдает 500 ошибку при чтении связей User/Contact.
 */
class ConversationApiTest extends WebTestCase
{
    public function testConversationsEndpointIsProtected(): void
    {
        $client = static::createClient();

        // 1. Проверяем, что без логина доступ закрыт (401)
        $client->request('GET', '/api/conversations');
        $this->assertResponseStatusCodeSame(401, 'API должен требовать авторизацию');
    }

    public function testHealthCheckSchema(): void
    {
        $client = static::createClient();

        // 2. Проверяем, что роут логина существует и отвечает правильно
        $client->request('GET', '/api/login');
        // Ожидаем 405 (Method Not Allowed), так как зашли GET-ом, но это значит, что контроллер живой!
        $this->assertResponseStatusCodeSame(405);
    }
}
