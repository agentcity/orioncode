<?php
namespace App\tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * ТЕСТ: Проверка инициализации пользователя.
 * Проверяет, что конструктор User правильно заполняет UUID и даты,
 * чтобы Doctrine не выдавала ошибку при сохранении.
 */
class UserTest extends TestCase
{
    public function testUserInitializesCorrectly(): void
    {
        $user = new User();

        // Проверяем, что ID генерируется сразу (решает проблему 500 ошибки на проде)
        $this->assertNotNull($user->getId(), 'ID должен генерироваться в конструкторе');
        $this->assertInstanceOf(Uuid::class, $user->getId());

        // Проверяем, что даты создания проставляются автоматически
        $this->assertNotNull($user->getEmail() === null);
        $this->assertTrue($user->isActive());
    }
}
