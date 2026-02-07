<?php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;

/**
 * ТЕСТ: Авторизация и получение JWT.
 * Проверяет создание пользователя и корректность ответа /api/login.
 */
class AuthTest extends WebTestCase
{
    public function testLoginReturnsJwtToken(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Очищаем старого тестового юзера, если он есть
        $oldUser = $em->getRepository(User::class)->findOneBy(['email' => 'test_auth@orioncode.ru']);
        if ($oldUser) { $em->remove($oldUser); $em->flush(); }

        $user = new User();
        $user->setEmail('test_auth_' . uniqid() . '@orioncode.ru');
        $user->setFirstName('Test'); // ОБЯЗАТЕЛЬНОЕ ПОЛЕ
        $user->setLastName('User');   // ОБЯЗАТЕЛЬНОЕ ПОЛЕ
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true); // Явно активируем, чтобы security не отфутболил

        $hasher = $container->get('security.password_hasher');
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        $client->request('POST', '/api/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $user->getEmail(),
                'password' => 'password123'
            ])
        );
        $response = $client->getResponse();
        if ($response->getStatusCode() !== 200) {
            dump($response->getContent()); // Это покажет ошибку в консоли
        }

        $this->assertResponseIsSuccessful();
        $data = json_decode($response->getContent(), true);

        // Если токена нет, выведи ответ в консоль для отладки
        if (!isset($data['token'])) {
            dump($data);
        }

        $this->assertArrayHasKey('token', $data);
    }
}
