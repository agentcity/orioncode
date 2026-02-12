<?php

namespace App\Tests\Functional;

use App\Entity\{Account, Contact, Message, User};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VkWebhookTest extends WebTestCase
{
    public function testVkConfirmationAndIncomingMessage(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // 1. ПОДГОТОВКА ДАННЫХ (Юзер + ВК Аккаунт)
        $email = 'vk_tester@orion.ru';
        $testUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$testUser) {
            $testUser = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_USER'])
                ->setPassword('pass')
                ->setFirstName('VK')
                ->setLastName('Admin');
            $em->persist($testUser);
        }

        $vkAccountId = 'd290f1ee-6c54-4b01-90e6-d701748f0851';
        $account = $em->getRepository(Account::class)->find($vkAccountId);

        if (!$account) {
            $account = new Account();
            // Если в твоем проекте используется другой метод установки ID, поправь здесь
            $account->setType('vk')
                ->setName('VK Test Acc')
                ->setStatus('active')
                ->setCredentials([
                    'vk_confirmation_code' => 'bab7f7e1',
                    'vk_token' => 'test_token'
                ])
                ->setUser($testUser);
            $em->persist($account);
        } else {
            $account->setCredentials(['vk_confirmation_code' => 'bab7f7e1']);
        }

        $em->flush();

        // 2. ЭТАП 1: ТЕСТ ПОДТВЕРЖДЕНИЯ (Confirmation)
        // ВК отправляет этот запрос при нажатии кнопки "Подтвердить" в кабинете
        $client->request('POST', "/api/webhooks/vk/{$account->getId()}", [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'confirmation', 'group_id' => 12345])
        );

        $this->assertResponseIsSuccessful();
        // Проверяем чистый текст без кавычек
        $this->assertEquals('bab7f7e1', $client->getResponse()->getContent(), 'Сервер должен вернуть чистый код подтверждения ВК');


        // 3. ЭТАП 2: ТЕСТ ВХОДЯЩЕГО СООБЩЕНИЯ (message_new)
        // Имитируем реальное сообщение от пользователя ВК
        $payload = [
            'type' => 'message_new',
            'object' => [
                'message' => [
                    'from_id' => 777,
                    'text' => 'Привет из ВК!'
                ]
            ],
            'group_id' => 12345
        ];

        $client->request('POST', "/api/webhooks/vk/{$account->getId()}", [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals('ok', $client->getResponse()->getContent(), 'ВК ждет ok после получения сообщения');


        // 4. ЭТАП 3: ПРОВЕРКА В БАЗЕ ДАННЫХ
        $em->clear(); // Очищаем кэш доктрины, чтобы увидеть данные из БД

        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '777', 'source' => 'vk']);
        $this->assertNotNull($contact, 'Контакт ВК должен быть создан в базе');

        $msg = $em->getRepository(Message::class)->findOneBy(['text' => 'Привет из ВК!']);
        $this->assertNotNull($msg, 'Сообщение из ВК должно быть сохранено в базе');
        $this->assertEquals('contact', $msg->getSenderType());
    }
}
