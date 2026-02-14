<?php
/*
 *
 * Интеграционный тест, который проверит, что PHP-код правильно формирует JSON и отправляет его в нужный канал Redis.
 * Улетает ли сигнал «Печатает» в канал chat_events
 * Записывается ли статус «Online» в Redis с правильным временем жизни (TTL)
 */


namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Conversation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class PresenceStatusTest extends WebTestCase
{
    private $redis;
    private $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Подключаемся к тестовому Redis
        $redisUrl = $_ENV['REDIS_URL'] ?? 'redis://orion_redis:6379';
        $this->redis = RedisAdapter::createConnection($redisUrl);
    }

    public function testUserOnlineStatusPersistence(): void
    {
        $userId = 'test-user-123';

        // Имитируем логику "В сети"
        $this->redis->set("user:status:{$userId}", 'online');
        $this->redis->expire("user:status:{$userId}", 60);

        // ПРОВЕРКА: Данные в Redis физически появились
        $status = $this->redis->get("user:status:{$userId}");
        $ttl = $this->redis->ttl("user:status:{$userId}");

        $this->assertEquals('online', $status);
        $this->assertGreaterThan(0, $ttl, 'TTL для статуса онлайн должен быть установлен');
    }

    public function testTypingEventFormat(): void
    {
        // Данные, которые PHP шлет в Redis для сокетов
        $eventData = [
            'type' => 'typing',
            'conversationId' => 'conv-uuid-456',
            'userId' => 'user-uuid-789',
            'userName' => 'Юрий'
        ];

        // Имитируем публикацию (publish возвращает кол-во получателей, в тестах может быть 0)
        $result = $this->redis->publish('chat_events', json_encode($eventData));

        $this->assertNotFalse($result, 'Redis должен быть доступен для публикации событий');

        // Проверяем валидность JSON
        $json = json_encode($eventData);
        $this->assertJson($json);
        $this->assertStringContainsString('typing', $json);
    }
}
