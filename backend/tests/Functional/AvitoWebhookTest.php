<?php

namespace App\Tests\Functional;
use App\Entity\Account;
use App\Entity\{Conversation, Message, Contact};
use App\Organization\Entity\Organization;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AvitoWebhookTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Чистим старое, чтобы не было дублей
        $this->em->createQuery('DELETE FROM App\Entity\Message')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Conversation')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Contact')->execute();
    }

    public function testAvitoIncomingMessageCreatesEverything(): void
    {
        // 1. Создаем организацию
        $org = (new Organization())->setName('Атаманский Двор');
        $this->em->persist($org);

        // 2. Создаем аккаунт Авито 🚀
        $account = (new Account())
            ->setName('Основной Авито')
            ->setType('avito')
            ->setOrganization($org)
            ->setStatus('active');
        $this->em->persist($account);
        $this->em->flush(); // Сохраняем, чтобы получить ID аккаунта

        // 3. Имитируем JSON (ВНИМАНИЕ: поправь структуру под свой контроллер!)
        // Твой контроллер ищет текст в $data['payload']['value']['text']
        $avitoPayload = [
            'payload' => [
                'value' => [
                    'id' => 'avito-msg-999',
                    'chat_id' => 'avito-chat-777',
                    'user_id' => '123456',
                    'author_id' => '123456',
                    'text' => 'Здравствуйте! Лошадь еще продается?'
                ]
            ]
        ];

        // 4. Шлем запрос, используя ID АККАУНТА 🚀
        $accountId = $account->getId()->toString();
        $this->client->request(
            'POST',
            "/api/webhooks/avito/{$accountId}",
            [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode($avitoPayload)
        );

        // ПРОВЕРКА 1: Ответ сервера 200/204
        $this->assertResponseIsSuccessful();

        // ПРОВЕРКА 2: Создался ли контакт (покупатель)
        $contact = $this->em->getRepository(Contact::class)->findOneBy(['externalId' => '123456']);
        $this->assertNotNull($contact, 'Контакт Авито не создан');
        $this->assertEquals('avito', $contact->getSource());

        // ПРОВЕРКА 3: Создалась ли беседа с правильной организацией
        $conversation = $this->em->getRepository(Conversation::class)->findOneBy(['contact' => $contact]);
        $this->assertNotNull($conversation, 'Беседа Авито не создана');
        $this->assertEquals($org->getId()->toString(), $conversation->getOrganization()->getId()->toString(), 'Организация не привязана к беседе Авито');

        // ПРОВЕРКА 4: Сохранилось ли сообщение
        $message = $this->em->getRepository(Message::class)->findOneBy(['conversation' => $conversation]);
        $this->assertNotNull($message);
        $this->assertStringContainsString('Лошадь еще продается', $message->getText());
    }
}
