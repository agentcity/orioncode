<?php
namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Conversation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ТЕСТ: Отправка сообщений.
 * Проверяет POST запрос на создание нового сообщения в существующей беседе.
 */
class MessageTest extends WebTestCase
{
    public function testSendMessageSuccessfully(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();


        // 1. Создаем Юзера
        $user = new User();
        $user->setEmail('tester_' . uniqid() . '@orioncode.ru');
        $user->setFirstName('Ivan');
        $user->setLastName('Ivanov');
        $user->setPassword('password');
        $em->persist($user);

        // 2. Создаем Аккаунт

        $account = new \App\Entity\Account();
        $account->setName('Test Company');
        $account->setType('business');
        $account->setStatus('active');
        $account->setUser($user); // <--- ПРИВЯЗКА ВЛАДЕЛЬЦА
        $em->persist($account);
        $em->flush();

        // 3. Создаем Контакт
        $contact = new Contact();
        $contact->setMainName('Client Name');
        $em->persist($contact);

        // 4. Создаем Беседу (Conversation)
        $conv = new Conversation();
        $conv->setAccount($account); // Привязываем сохраненный аккаунт
        $conv->setAssignedTo($user);
        $conv->setContact($contact);
        $conv->setStatus('open');
        $conv->setType('direct');
        $conv->setExternalId('conv_' . uniqid());
        $em->persist($conv);

        $em->flush();


        $client->loginUser($user);

        $conversationId = $conv->getId()->toString();

        $client->request(
            'POST',
            "/api/conversations/$conversationId/messages",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'conversationId' => $conv->getId()->toString(),
                'text' => 'Test message',
                'type' => 'text'
            ])
        );

        $this->assertResponseIsSuccessful();
    }
}
