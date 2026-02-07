<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Contact;

/**
 * ТЕСТ: Список чатов.
 * Проверяет, что контроллер может собрать JSON из связей User-Conversation-Contact.
 */
class ConversationTest extends WebTestCase
{
    public function testConversationsListStructure(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();


        $user = new User();
        $user->setEmail('chat_tester_' . uniqid() . '@orioncode.ru');
        $user->setFirstName('Chat');
        $user->setLastName('Tester');
        $user->setPassword('fake');
        $em->persist($user);

        $account = new \App\Entity\Account();
        $account->setName('Test Company');
        $account->setType('business');
        $account->setStatus('active');
        $account->setUser($user); // <--- ПРИВЯЗКА ВЛАДЕЛЬЦА
        $em->persist($account);
        $em->flush();

        $contact = new Contact();
        $contact->setMainName('Target Contact');
        $em->persist($contact);

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
        $client->request('GET', '/api/conversations');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        // Проверяем первый элемент списка
        $this->assertEquals('Target Contact', $data[0]['contact']['mainName']);
    }
}
