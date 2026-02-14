<?php

namespace App\Tests\Functional;

use App\Entity\{User, Conversation, Message};
use App\Organization\Entity\Organization;
use App\Service\ChatService;
use App\Service\AI\AiModelInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AiBillingTest extends WebTestCase
{
    private ?EntityManagerInterface $em;
    private $chatService;

    protected function setUp(): void
    {
        // 1. Инициализируем ядро
        self::bootKernel();
        $container = static::getContainer();

        // 2. СНАЧАЛА создаем Mock
        $aiMock = $this->createMock(AiModelInterface::class);
        $aiMock->method('ask')->willReturn('Тестовый ответ ИИ (бесплатно)');

        // 3. ПОДМЕНЯЕМ сервис в контейнере ДО того, как достанем ChatService 🚀
        // Это предотвращает Notice "service already initialized"
        $container->set(AiModelInterface::class, $aiMock);

        $this->em = $container->get('doctrine.orm.entity_manager');
        $this->chatService = $container->get(ChatService::class);

        // 4. ОЧИСТКА БАЗЫ
        $this->em->createQuery('DELETE FROM App\Entity\Message')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Conversation')->execute();
        $this->em->createQuery('DELETE FROM App\Organization\Entity\Organization')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :email')
            ->setParameter('email', '%@test.com')->execute();
    }


    /**
     * Сценарий 1: Организация платит за ИИ, личный баланс юзера не трогаем
     */
    #[WithoutErrorHandler]
    public function testOrganizationPaysForAi(): void
    {
        $org = (new Organization())->setName('PayOrg')->setBalance(10.00);
        $user = (new User())->setEmail('manager@test.com')->setFirstName('Ivan')->setLastName('O')->setPassword('123')->setBalance(50.00);

        $this->em->persist($org);
        $this->em->persist($user);

        $conv = (new Conversation())->setType('vk')->setAssignedTo($user)->setOrganization($org);
        $this->em->persist($conv);
        $this->em->flush();

        $this->chatService->generateAiReply($conv, 'Привет!');

        $this->em->refresh($org);
        $this->em->refresh($user);
        $this->assertEquals(8.00, $org->getBalance(), 'У организации должно списаться 2 рубля');
        $this->assertEquals(50.00, $user->getBalance(), 'Личный баланс менеджера не должен измениться');
    }

    /**
     * Сценарий 2: Одинокий пользователь без организации платит сам за себя
     */
    #[WithoutErrorHandler]
    public function testSoloUserPaysPersonal(): void
    {
        $user = (new User())->setEmail('solo_rich@test.com')->setFirstName('Solo')->setLastName('R')->setPassword('123')->setBalance(10.00);
        $this->em->persist($user);

        $conv = (new Conversation())->setType('orion')->setAssignedTo($user);
        $this->em->persist($conv);
        $this->em->flush();

        $this->chatService->generateAiReply($conv, 'Нужна помощь');

        $this->em->refresh($user);
        $this->assertEquals(8.00, $user->getBalance(), 'Личный баланс должен уменьшиться');
    }

    /**
     * Сценарий 3: Если баланс Организации 0, личный баланс юзера НЕ ТРОГАЕМ
     */
    #[WithoutErrorHandler]
    public function testOrgZeroBalanceDoesNotTouchUserPersonalBalance(): void
    {
        $org = (new Organization())->setName('EmptyOrg')->setBalance(0.00);
        $user = (new User())->setEmail('rich_manager@test.com')->setFirstName('Rich')->setLastName('M')->setPassword('123')->setBalance(1000.00);

        $this->em->persist($org);
        $this->em->persist($user);

        $conv = (new Conversation())->setType('orion')->setAssignedTo($user)->setOrganization($org);
        $this->em->persist($conv);
        $this->em->flush();

        $this->chatService->generateAiReply($conv, 'Рабочий вопрос');

        $this->em->refresh($org);
        $this->em->refresh($user);

        // Проверяем сервисное сообщение
        // Находим последнее сообщение от бота
        $messages = $this->em->getRepository(Message::class)->findBy(
            ['conversation' => $conv],
            ['sentAt' => 'DESC']
        );

        $this->assertNotEmpty($messages, 'Бот не создал ни одного сообщения!');
        $lastMessageText = $messages[0]->getText();

        // 🚀 ГИБКАЯ ПРОВЕРКА (убираем возможные нотисы кодировок):
        $this->assertStringContainsString('EmptyOrg', $lastMessageText);
        $this->assertStringContainsString('исчерпан', $lastMessageText);

        // 2. Сравнение баланса (используй float для assertEquals, чтобы не было Notice по типам)
        $this->assertEquals(1000.0, (float)$user->getBalance());
        $this->assertEquals(0.0, (float)$org->getBalance());
    }

    /**
     * Сценарий 4: Одиночка без баланса получает уведомление и не платит в минус
     */
    #[WithoutErrorHandler]
    public function testSoloUserNoBalanceError(): void
    {
        $user = (new User())->setEmail('solo_poor@test.com')->setFirstName('Solo')->setLastName('P')->setPassword('123')->setBalance(0.50);
        $this->em->persist($user);

        $conv = (new Conversation())->setType('orion')->setAssignedTo($user);
        $this->em->persist($conv);
        $this->em->flush();

        $this->chatService->generateAiReply($conv, 'Помоги!');

        $this->em->refresh($user);
        $messages = $this->em->getRepository(Message::class)->findBy(['conversation' => $conv]);

        $this->assertStringContainsString('Ваш личный баланс исчерпан', $messages[0]->getText());
        $this->assertEquals(0.50, $user->getBalance(), 'Баланс не должен уйти в минус');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null;
    }
}
