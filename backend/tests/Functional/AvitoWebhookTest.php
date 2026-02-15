<?php

namespace App\Tests\Functional;
use App\Entity\Account;
use App\Entity\{Conversation, Message, Contact};
use App\Organization\Entity\Organization;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AvitoWebhookTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Чистим старое, чтобы не было дублей
        $this->em->createQuery('DELETE FROM App\Entity\Message')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Conversation')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Contact')->execute();
    }

    public function testAvitoIncomingMessageCreatesEverything(): void
    {
        // 1. Создаем данные
        $org = (new Organization())->setName('Атаманский Двор');
        $this->em->persist($org);

        $account = (new Account())
            ->setName('Основной Авито')
            ->setType('avito')
            ->setOrganization($org)
            ->setStatus('active');
        $this->em->persist($account);
        $this->em->flush();

        $avitoPayload = [
            'payload' => [
                'value' => [
                    'id' => 'avito-msg-999',
                    'chat_id' => 'avito-chat-777',
                    'user_id' => '123456',
                    'author_id' => '1234567',
                    'text' => 'Здравствуйте! Лошадь еще продается?'
                ]
            ]
        ];

        // 2. Шлем запрос
        $this->client->request(
            'POST',
            '/api/webhooks/avito/' . $account->getId()->toString(),
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($avitoPayload)
        );

        $this->assertResponseIsSuccessful();

        // 🚀 ГЛАВНЫЙ ФИКС: Достаем EM из клиента, чтобы увидеть изменения!
        $testEm = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $testEm->clear(); // Очищаем, чтобы прочитать из БД

        // 3. ПРОВЕРКА
        $contact = $testEm->getRepository(Contact::class)->findOneBy(['externalId' => '123456']);

        // Если тут всё еще null — значит контроллер не зашел в if(isset)
        $this->assertNotNull($contact, 'Контакт не найден. Проверь структуру JSON в контроллере!');

        $conversation = $testEm->getRepository(Conversation::class)->findOneBy(['contact' => $contact]);
        $this->assertNotNull($conversation, 'Беседа не создана');
        $this->assertEquals($org->getId()->toString(), $conversation->getOrganization()->getId()->toString());
    }


    public function testAvitoTokenGeneration(): void
    {
        $account = $this->em->getRepository(Account::class)->findOneBy(['type' => 'avito']);

        if (!$account) {
            $account = (new Account())->setType('avito')->setName('Token Test');
            $this->em->persist($account);
        }

        // 🚀 ГЛАВНЫЙ ФИКС: Явно прописываем ключи прямо перед проверкой
        $account->setCredentials([
            'client_id' => 'rk0uNyHvqY2M-xYIHzMZ',
            'client_secret' => 'MX2my6R0xFQRDEQdNCzfCePzMnc_gJ0b6WMUo-ec'
        ]);
        $this->em->flush();

        // 1. Создаем МОК для HttpClient, чтобы не ходить в реальный Авито
        $mockResponse = $this->createMock(\Symfony\Contracts\HttpClient\ResponseInterface::class);
        $mockResponse->method('toArray')->willReturn(['access_token' => 'test_token_123']);

        $mockHttpClient = $this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class);
        $mockHttpClient->method('request')->willReturn($mockResponse);


        // 1. Создаем мок кэша
        $cacheMock = $this->createMock(\Symfony\Contracts\Cache\CacheInterface::class);
        // Метод get просто должен возвращать результат коллбэка
        $cacheMock->method('get')->willReturnCallback(fn($key, $callback) => $callback(new \Symfony\Component\Cache\CacheItem()));

        // 2. Передаем ДВА аргумента в конструктор 🚀
        $avitoService = new \App\Messenger\Service\AvitoTokenService($mockHttpClient, $cacheMock);


        // 2. Достаем credentials
        $creds = $account->getCredentials();

        // 🚀 ПРОВЕРКА: Если credentials пустые, тест упадет тут с понятной ошибкой
        $this->assertArrayHasKey('client_id', $creds, 'В аккаунте нет client_id');
        $this->assertArrayHasKey('client_secret', $creds, 'В аккаунте нет client_secret');

        // 3. Вызываем сервис (теперь $clientId точно string)
        $token = $avitoService->getAccessToken(
            (string)$creds['client_id'],
            (string)$creds['client_secret'],
            $account->getId()->toString()
        );

        $this->assertEquals('test_token_123', $token);
    }


}
