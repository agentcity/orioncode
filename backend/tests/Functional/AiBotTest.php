<?php
namespace App\Tests\Functional;

use App\Entity\{Account, Contact, Conversation, User, Message};
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AiBotTest extends WebTestCase {
    public function testAiBotResponse(): void {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $chatService = $container->get(ChatService::class);

        // 1. ЮЗЕР (Менеджер)
        $email = 'bot_tester@orion.ru';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = (new User())->setEmail($email)->setFirstName('AI')->setLastName('Tester')->setPassword('123');
            $em->persist($user);
        }

        // 2. АККАУНТ
        $account = $em->getRepository(Account::class)->findOneBy(['name' => 'AI Test Acc']);
        if (!$account) {
            $account = (new Account())->setType('telegram')->setName('AI Test Acc')->setStatus('active')->setUser($user);
            $em->persist($account);
        }

        // 3. КОНТАКТ
        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '555']);
        if (!$contact) {
            $contact = (new Contact())->setMainName('Human Client')->setSource('telegram')->setExternalId('555')->setAccount($account);
            $em->persist($contact);
        }

        // 4. БЕСЕДА
        $conv = $em->getRepository(Conversation::class)->findOneBy(['contact' => $contact]);
        if (!$conv) {
            $conv = (new Conversation())->setContact($contact)->setAccount($account)->setType('telegram')->setStatus('active');
            $em->persist($conv);
        }

        $em->flush();

        // 5. САМ ТЕСТ
        $chatService->generateAiReply($conv, 'Привет, Кот! Проверка логики.');

        // 6. ПРОВЕРКА ОТВЕТА
        $botMsg = $em->getRepository(Message::class)->findOneBy([
            'conversation' => $conv,
            'senderType' => 'bot'
        ], ['sentAt' => 'DESC']);

        $this->assertNotNull($botMsg, 'Орион Кот не создал ответ в базе');
        $this->assertNotEmpty($botMsg->getText(), 'Текст ответа ИИ не должен быть пустым');
    }
}
