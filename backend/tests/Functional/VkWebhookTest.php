<?php
namespace App\Tests\Functional;

use App\Entity\{Account, Contact, Message, User};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class VkWebhookTest extends WebTestCase {
    public function testVkConfirmationAndIncomingMessage(): void {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        // 1. ПОДГОТОВКА (Юзер + ВК Аккаунт)
        $email = 'vk_tester@orion.ru';
        $testUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$testUser) {
            $testUser = (new User())
                ->setEmail($email)
                ->setRoles(['ROLE_USER'])
                ->setPassword('pass')
                ->setFirstName('VK')
                ->setLastName('Admin');
            $em->persist($testUser); // Сохраняем нового юзера
        }

        $vkAccountId = 'd290f1ee-6c54-4b01-90e6-d701748f0851';
        $account = $em->getRepository(Account::class)->find($vkAccountId);

        if (!$account) {
            $account = (new Account())
                ->setType('vk')
                ->setName('VK Test Shop')
                ->setStatus('active')
                ->setCredentials(['vk_confirmation_code' => 'test_code_123', 'vk_token' => 'test_token'])
                ->setUser($testUser); // Теперь юзер уже известен Doctrine
            $em->persist($account);
        }

        $em->flush();

        // 2. ТЕСТ ПОДТВЕРЖДЕНИЯ (Confirmation)
        $client->request('POST', "/api/webhooks/vk/{$account->getId()}", [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'confirmation', 'group_id' => 12345])
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals('"test_code_123"', $client->getResponse()->getContent(), 'Сервер должен вернуть код подтверждения ВК');

        // 3. ТЕСТ ВХОДЯЩЕГО СООБЩЕНИЯ (message_new)
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

        // Проверяем БД
        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '777', 'source' => 'vk']);
        $this->assertNotNull($contact, 'Контакт ВК должен быть создан');

        $msg = $em->getRepository(Message::class)->findOneBy(['text' => 'Привет из ВК!']);
        $this->assertNotNull($msg, 'Сообщение из ВК должно быть в базе');
    }
}
