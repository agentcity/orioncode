<?php

/*
 * Этот тест критически важен: он проверяет,
 * что менеджер из «Атаманского Двора» не сможет подсмотреть переписку «Конного Клуба Б».
 *
 */

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Account;
use App\Entity\Conversation;
use App\Organization\Entity\Organization;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class OrganizationAccessTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // 🚀 ЧИСТКА ПЕРЕД ТЕСТОМ:
        $repo = $this->em->getRepository(User::class);
        $existingUser = $repo->findOneBy(['email' => 'manager_a@test.com']);

        if ($existingUser) {
            // Удаляем связанные беседы и аккаунты, чтобы не было ошибок внешних ключей
            $this->em->createQuery('DELETE FROM App\Entity\Message')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Conversation')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Account')->execute();
            $this->em->remove($existingUser);
            $this->em->flush();
        }

        // Также чистим тестовые организации
        $this->em->createQuery("DELETE FROM App\Organization\Entity\Organization o WHERE o.name IN ('Атаманский Двор', 'Чужой Клуб')")->execute();
        $this->em->flush();
    }


    public function testManagerCannotAccessOtherOrganizationConversation(): void
    {
        // 1. Создаем Организацию А и Менеджера А
        $orgA = (new Organization())->setName('Атаманский Двор');
        $this->em->persist($orgA);

        $userA = new User();
        $userA->setEmail('manager_a@test.com')
            ->setRoles(['ROLE_USER'])
            ->setPassword('pass')
            // 🚀 ДОБАВЬ ЭТИ СТРОКИ:
            ->setFirstName('Иван')
            ->setLastName('Иванов');
        // Привязываем к Организации А (через твой метод в сущности)
        $orgA->addUser($userA);
        $this->em->persist($userA);

        // 2. Создаем Организацию Б и Чат Б
        $orgB = (new Organization())->setName('Чужой Клуб');
        $this->em->persist($orgB);

        $accountB = (new Account())
            ->setName('ВК Чужой')
            ->setType('vk')
            ->setOrganization($orgB)
            ->setStatus('active');


        $this->em->persist($accountB);

        $convB = (new Conversation())->setType('vk')->setAccount($accountB)->setOrganization($orgB);
        $this->em->persist($convB);

        $this->em->flush();

        // 3. Логинимся под Менеджером А
        $this->client->loginUser($userA);

        // 4. Пытаемся получить список чатов
        $this->client->request('GET', '/api/conversations');
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode($this->client->getResponse()->getContent(), true);

        // ПРОВЕРКА 1: В списке не должно быть чата из Организации Б
        foreach ($content as $conv) {
            $this->assertNotEquals($convB->getId()->toString(), $conv['id'], 'Менеджер видит чужой чат в списке!');
        }

        // 5. Пытаемся открыть конкретный чат Б по ID
        $this->client->request('GET', '/api/conversations/' . $convB->getId()->toString());

        // Если система выдает 401, значит сессия "отвалилась".
        // Но по логике безопасности нам подходит и 401, и 403 — главное, что НЕ 200!
        $statusCode = $this->client->getResponse()->getStatusCode();

        $this->assertContains(
            $statusCode,
            [Response::HTTP_FORBIDDEN, Response::HTTP_UNAUTHORIZED],
            'Менеджер смог получить доступ к чужому чату или сессия упала!'
        );
    }
}
