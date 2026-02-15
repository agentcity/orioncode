<?php

namespace App\Tests\Functional\System;

use App\Entity\User;
use App\Organization\Entity\Organization;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class RegistrationTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Чистим тестового юзера, если он остался от прошлых запусков
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'newuser@orion.ru']);
        if ($user) {
            $org = $user->getOrganizations()->first();
            $this->em->remove($user);
            if ($org) $this->em->remove($org);
            $this->em->flush();
        }
    }

    public function testUserRegistrationCreatesStubOrganization(): void
    {
        // 1. Имитируем запрос с фронтенда
        $payload = [
            'email' => 'newuser@orion.ru',
            'password' => 'SafePassword123',
            'firstName' => 'Юрий'
        ];

        $this->client->request(
            'POST',
            '/api/register',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        // 2. ПРОВЕРКА: Статус 201 Created
        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        // 3. ПРОВЕРКА БАЗЫ: Создался ли юзер?
        $this->em->clear(); // Очищаем кэш Doctrine
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'newuser@orion.ru']);

        $this->assertNotNull($user, 'Пользователь не был создан в базе данных');
        $this->assertEquals('Юрий', $user->getFirstName());

        // 4. ПРОВЕРКА ОРГАНИЗАЦИИ: Создалась ли заглушка?
        $orgs = $user->getOrganizations(); // Получаем коллекцию
        $this->assertCount(1, $orgs, 'У пользователя должна быть ровно 1 организация-заглушка');
        $org = $orgs->first(); // Берем первую
        $this->assertStringContainsString('Личное пространство Юрий', $org->getName());

        // 5. ПРОВЕРКА БАЛАНСА: Начислено ли 100 рублей? 🚀
        $this->assertEquals(100.00, (float)$org->getBalance(), 'Приветственный баланс не равен 100.00');
    }
}
