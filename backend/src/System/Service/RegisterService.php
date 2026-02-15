<?php

namespace App\System\Service;

use App\Entity\User;
use App\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {}

    public function register(string $email, string $password, string $firstName): User
    {

        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            throw new \Exception('USER_ALREADY_EXISTS');
        }

        // 1. Создаем Организацию-заглушку (Личное пространство) 🎁
        $org = new Organization();
        $org->setName("Личное пространство " . $firstName);
        $org->setBalance(100.00); // Приветственный бонус Orion 2026


        // 2. Создаем Юзера
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName('');
        $user->setRoles(['ROLE_USER']);
        $user->addOrganization($org); // Привязываем к заглушке

        $hashedPassword = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $org->addUser($user);

        $this->em->persist($org);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
