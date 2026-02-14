<?php

namespace App\Tests\Functional;

use App\Entity\{Account, Contact, Conversation, User, Message};
use App\Organization\Entity\Organization; // 🚀 ВАЖНО: Новый импорт
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Ramsey\Uuid\Uuid;

class AiBotTest extends WebTestCase {
    public function testAiBotResponse(): void {
        self::bootKernel();
        $container = static::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $chatService = $container->get(ChatService::class);

        // 0. ОЧИСТКА (чтобы тесты не конфликтовали)
        $em->createQuery('DELETE FROM App\Entity\Message')->execute();

        // 1. ОРГАНИЗАЦИЯ (с балансом!)
        $orgName = 'Test AI Organization';
        $org = $em->getRepository(Organization::class)->findOneBy(['name' => $orgName]);
        if (!$org) {
            $org = (new Organization())
                ->setName($orgName)
                ->setBalance(100.00); // 💰 Даем денег на ИИ
            $em->persist($org);
        } else {
            $org->setBalance(100.00);
        }

        // 2. ЮЗЕР (Менеджер)
        $email = 'bot_tester@orion.ru';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = (new User())
                ->setEmail($email)
                ->setFirstName('AI')
                ->setLastName('Tester')
                ->setRoles(['ROLE_USER'])
                ->setPassword('123');
            $em->persist($user);
        }
        $org->addUser($user);

        // 3. АККАУНТ (привязанный к организации)
        $accountName = 'AI Test Acc';
        $account = $em->getRepository(Account::class)->findOneBy(['name' => $accountName]);
        if (!$account) {
            $account = (new Account())
                ->setType('telegram')
                ->setName($accountName)
                ->setStatus('active')
                ->setOrganization($org); // 🚀 СВЯЗЬ С ОРГ
            $em->persist($account);
        }

        // 4. КОНТАКТ
        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => '555']);
        if (!$contact) {
            $contact = (new Contact())
                ->setMainName('Human Client')
                ->setSource('telegram')
                ->setExternalId('555')
                ->setAccount($account);
            $em->persist($contact);
        }

        // 5. БЕСЕДА (денормализованная с организацией)
        $conv = $em->getRepository(Conversation::class)->findOneBy(['contact' => $contact]);
        if (!$conv) {
            $conv = (new Conversation())
                ->setContact($contact)
                ->setAccount($account)
                ->setOrganization($org) // 🚀 ПРЯМАЯ СВЯЗЬ (Денормализация)
                ->setType('telegram')
                ->setStatus('active');
            $em->persist($conv);
        }

        $em->flush();

        // 6. ЗАПУСК ГЕНЕРАЦИИ ОТВЕТА
        $chatService->generateAiReply($conv, 'Привет, Кот! Проверка логики.');

        // Перечитываем из базы, чтобы увидеть изменения
        $em->clear();

        // 7. ПРОВЕРКА ОТВЕТА
        $botMsg = $em->getRepository(Message::class)->findOneBy([
            'conversation' => $conv->getId(),
            'senderType' => 'bot'
        ], ['sentAt' => 'DESC']);

        $this->assertNotNull($botMsg, 'Орион Кот не создал ответ в базе (возможно, из-за баланса или отсутствия организации)');
        $this->assertNotEmpty($botMsg->getText(), 'Текст ответа ИИ не должен быть пустым');

        // Дополнительная проверка: баланс должен уменьшиться
        $updatedOrg = $em->getRepository(Organization::class)->find($org->getId());
        $this->assertEquals(98.00, $updatedOrg->getBalance(), 'Баланс организации не списался за ответ ИИ');
    }
}
