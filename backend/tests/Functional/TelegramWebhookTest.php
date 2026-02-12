<?php
namespace App\Tests\Functional;

use App\Entity\{Account, Contact, Message, User};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TelegramWebhookTest extends WebTestCase {
    public function testIncomingTelegramMessage(): void {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // 1. ПОЛНАЯ ЗАЧИСТКА ПЕРЕД ТЕСТОМ
        // Сначала удаляем контакт, чтобы не было конфликта внешних ключей
        $oldContact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '888777']);
        if ($oldContact) {
            $em->remove($oldContact);
            $em->flush();
        }

        // Теперь удаляем старый тестовый аккаунт, если он остался от прошлых запусков
        $oldAccount = $em->getRepository(Account::class)->findOneBy(['name' => 'Test Shop']);
        if ($oldAccount) {
            $em->remove($oldAccount);
            $em->flush();
        }


        // 1. ПОДГОТОВКА ЮЗЕРА
        $email = 'test_manager@orion.ru';
        $testUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$testUser) {
            $testUser = (new User())->setEmail($email)->setRoles(['ROLE_USER'])->setPassword('pass')
                ->setFirstName('Admin')->setLastName('Test');
            $em->persist($testUser);
        }

        // 2. ПОДГОТОВКА АККАУНТА (Берем существующий или создаем)
        $account = $em->getRepository(Account::class)->findOneBy(['name' => 'Test Shop']);
        if (!$account) {
            $account = (new Account())->setType('telegram')->setName('Test Shop')->setStatus('active')->setUser($testUser);
            $em->persist($account);
        }

        // 3. ЧИСТКА СТАРОГО КОНТАКТА (Чтобы проверить именно НОВОЕ привязывание в этом тесте)
        // Если хочешь проверить создание с нуля - удаляй.
        // Если хочешь проверить работу с существующим - закомментируй этот блок.
        $oldContact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '888777']);
        if ($oldContact) {
            $em->remove($oldContact);
        }

        $em->flush();

        // 4. ИМИТИРУЕМ ВЕБХУК
        $payload = [
            'message' => [
                'from' => ['id' => 888777, 'first_name' => 'Client'],
                'text' => 'Hello Bot'
            ]
        ];

        $client->request('POST', "/api/webhooks/telegram/{$account->getId()}",
            [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

        // 5. ПРОВЕРКИ
        // 1. ПРОВЕРКА: Если тут не 200, тест покажет нам текст ошибки Symfony
        if ($client->getResponse()->getStatusCode() !== 200) {
            echo $client->getResponse()->getContent();
        }
        $this->assertResponseIsSuccessful();

        // 2. Ищем контакт (теперь используем свежий Entity Manager)
        $em->clear(); // Очищаем кэш доктрины, чтобы она увидела данные из БД
        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '888777']);

        // 3. Теперь проверяем
        $this->assertNotNull($contact, 'Контакт НЕ БЫЛ СОЗДАН контроллером. Проверь логи!');


        // Теперь ID точно совпадут, так как мы либо создали один аккаунт, либо взяли существующий
        $this->assertEquals(
            $account->getId()->toString(),
            $contact->getAccount()->getId()->toString(),
            'Контакт должен быть привязан к актуальному аккаунту'
        );

        $msg = $em->getRepository(Message::class)->findOneBy(['text' => 'Hello Bot'], ['sentAt' => 'DESC']);
        $this->assertNotNull($msg, 'Сообщение должно быть в базе');
    }
}
