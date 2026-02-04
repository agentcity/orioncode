<?php

namespace App\Controller\Api;

use App\Entity\Account;
use App\Entity\Contact;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\AccountRepository;
use App\Repository\ContactRepository;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Predis\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookController extends AbstractController
{
    #[Route('/api/webhooks/telegram', name: 'webhook_telegram', methods: ['POST'])]
    public function handle(
        Request $request,
        EntityManagerInterface $em,
        AccountRepository $accountRepository,
        ContactRepository $contactRepository,
        ConversationRepository $conversationRepository,
        ClientInterface $redisClient
    ): Response {
        $payload = json_decode($request->getContent(), true);

        // Проверяем, что это текстовое сообщение
        if (!isset($payload['message']['text'])) {
            return new Response('Event ignored');
        }

        $chatId = (string)$payload['message']['chat']['id'];
        $text = $payload['message']['text'];
        $firstName = $payload['message']['from']['first_name'] ?? 'Telegram User';
        $lastName = $payload['message']['from']['last_name'] ?? '';

        // 1. Ищем Telegram аккаунт (интеграцию)
        $account = $accountRepository->findOneBy(['type' => 'telegram']);
        if (!$account) {
            return new Response('Telegram account not configured', 404);
        }

        // 2. Ищем или создаем контакт
        // Для простоты ищем по externalId в метаданных или создаем новый
        // В реальной системе лучше иметь отдельное поле externalId в Contact
        $contact = $contactRepository->findOneBy(['mainName' => $firstName . ' ' . $lastName])
            ?? new Contact();

        if (!$contact->getId()) {
            $contact->setMainName($firstName . ' ' . $lastName);
            $contact->setFirstName($firstName);
            $contact->setLastName($lastName);
            $em->persist($contact);
        }

        // 3. Ищем или создаем беседу (Conversation)
        $conversation = $conversationRepository->findOneBy([
            'externalId' => $chatId,
            'type' => 'telegram',
            'account' => $account
        ]);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setAccount($account);
            $conversation->setContact($contact);
            $conversation->setExternalId($chatId);
            $conversation->setType('telegram');
            $conversation->setStatus('open');
            $conversation->setUnreadCount(0);
            $conversation->setAssignedTo($account->getUser()); // Назначаем на владельца аккаунта
            $em->persist($conversation);
        }

        // 4. Создаем входящее сообщение
        $message = new Message();
        $message->setConversation($conversation);
        $message->setText($text);
        $message->setDirection('incoming');
        $message->setSenderType('contact');
        $message->setStatus('delivered');
        $message->setIsRead(false);
        $message->setSentAt(new \DateTimeImmutable());
        $message->setExternalId((string)$payload['message']['message_id']);

        // Обновляем беседу
        $conversation->setUnreadCount($conversation->getUnreadCount() + 1);
        $conversation->setLastMessageAt($message->getSentAt());

        $em->persist($message);
        $em->flush();

        // 5. Пушим в WebSocket через Redis
        $redisClient->publish('new_message_channel', json_encode([
            'id' => $message->getId()->toString(),
            'text' => $message->getText(),
            'conversationId' => $conversation->getId()->toString(),
            'assignedToId' => $conversation->getAssignedTo() ? $conversation->getAssignedTo()->getId()->toString() : null,
            'direction' => 'incoming',
            'type' => 'telegram',
            'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
        ]));

        return new Response('OK');
    }
}
