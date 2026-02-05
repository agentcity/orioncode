<?php

namespace App\DataFixtures;

use App\Entity\Account;
use App\Entity\Attachment;
use App\Entity\Contact;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use DateTimeImmutable;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // 1. Создаем тестового пользователя (Администратора)
        $user = new User();
        $user->setEmail('a@a.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $user->setRoles(['ROLE_ADMIN']);
        $user->setFirstName('Admin');
        $user->setLastName('Orion');
        $user->setIsActive(true);
        $manager->persist($user);

        // 2. Создаем аккаунт (Интеграция, например WhatsApp)
        $account = new Account();
        $account->setUser($user);
        $account->setType('whatsapp');
        $account->setName('Рабочий WhatsApp');
        $account->setStatus('active');
        $account->setExternalId('WA-SERVER-001');
        $account->setCredentials(['token' => 'secret_token_123']);
        $manager->persist($account);

        // 3. Данные для генерации контактов и чатов
        $contactsData = [
            ['name' => 'Александр Пушкин', 'phone' => '79001112233'],
            ['name' => 'Лев Толстой', 'phone' => '79004445566'],
            ['name' => 'Анна Ахматова', 'phone' => '79007778899'],
        ];

        foreach ($contactsData as $index => $data) {
            // Создаем контакт
            $contact = new Contact();
            $contact->setMainName($data['name']);
            $contact->setFirstName(explode(' ', $data['name'])[0]);
            $contact->setLastName(explode(' ', $data['name'])[1] ?? null);
            $manager->persist($contact);

            // Создаем беседу
            $conversation = new Conversation();
            $conversation->setAccount($account);
            $conversation->setContact($contact);
            $conversation->setExternalId($data['phone']);
            $conversation->setType('whatsapp');
            $conversation->setStatus('open');
            $conversation->setUnreadCount(rand(1, 5));
            $conversation->setLastMessageAt(new DateTimeImmutable('-' . ($index + 1) . ' hours'));
            $conversation->setAssignedTo($user);
            $manager->persist($conversation);

            // 4. Генерируем сообщения для каждой беседы
            for ($j = 0; $j < 10; $j++) {
                $message = new Message();
                $message->setConversation($conversation);
                $message->setDirection($j % 2 === 0 ? 'incoming' : 'outgoing');
                $message->setSenderType($j % 2 === 0 ? 'contact' : 'user');
                $message->setSenderId($j % 2 === 0 ? $contact->getId() : $user->getId());
                $message->setText("Это тестовое сообщение номер " . ($j + 1) . " для чата с " . $data['name']);
                $message->setIsRead(true);
                $message->setSentAt(new DateTimeImmutable('-' . (20 - $j) . ' minutes'));
                $message->setExternalId('MSG-EXT-' . uniqid());

                $manager->persist($message);

                // Добавляем вложение к каждому 5-му сообщению
                if ($j % 5 === 0) {
                    $attachment = new Attachment();
                    $attachment->setMessage($message);
                    $attachment->setType('image');
                    $attachment->setFileName('image_' . $j . '.jpg');
                    $attachment->setFileSize(204800); // 200 KB
                    $attachment->setMimeType('image/jpeg');
                    $attachment->setUrl('https://picsum.photos' . uniqid() . '/400/300');
                    $manager->persist($attachment);
                }
            }
        }

        // Сохраняем всё в базу данных
        $manager->flush();
    }
}
