<?php


namespace App\MessageHandler;

use App\Entity\Contact;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Message\IncomingTelegramMessage;
use App\Message\NewMessageNotification;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;


#[AsMessageHandler]
class IncomingTelegramMessageHandler
{
    private AccountRepository $accountRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageBusInterface $messageBus;


    public function __construct(
        AccountRepository $accountRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        MessageBusInterface $messageBus
    ) {
        $this->accountRepository = $accountRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->messageBus = $messageBus;
    }

    public function __invoke(IncomingTelegramMessage $message)
    {
        $token = $message->getToken();
        $payload = $message->getPayload();

        // 1. Найти Account по токену
        $account = $this->accountRepository->findOneBy(['credentials.token' => $token]
        ); // Потребуется кастомный репозиторий или дешифровка
        if (!$account) {
            $this->logger->warning('Telegram account not found for token: ' . $token);
            return;
        }

        // 2. Парсинг payload Telegram
        $update = $payload;
        if (!isset($update['message'])) {
            $this->logger->info('Telegram update without message: ' . json_encode($update));
            return; // Или обработать другие типы обновлений (callback_query, etc.)
        }

        $telegramMessage = $update['message'];
        $chatId = $telegramMessage['chat']['id'];
        $fromId = $telegramMessage['from']['id'];
        $text = $telegramMessage['text'] ?? null;
        $senderName = $telegramMessage['from']['first_name'] ?? 'Unknown';

        // 3. Найти или создать Contact
        $contact = $this->entityManager->getRepository(Contact::class)->findOneBy(
            ['externalId' => (string)$fromId, 'type' => 'telegram']
        );
        if (!$contact) {
            $contact = new Contact();
            $contact->setExternalId((string)$fromId);
            $contact->setType('telegram');
            $contact->setMainName($senderName);
            $contact->setFirstName($telegramMessage['from']['first_name'] ?? null);
            $contact->setLastName($telegramMessage['from']['last_name'] ?? null);
            $this->entityManager->persist($contact);
        }

        // 4. Найти или создать Conversation
        $conversation = $this->entityManager->getRepository(Conversation::class)->findOneBy([
            'account' => $account,
            'externalId' => (string)$chatId,
            'type' => 'telegram'
        ]);
        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setAccount($account);
            $conversation->setContact($contact);
            $conversation->setExternalId((string)$chatId);
            $conversation->setType('telegram');
            $conversation->setStatus('open');
            $conversation->setUnreadCount(0);
            $this->entityManager->persist($conversation);
        }

        // 5. Создать и сохранить Message
        $messageEntity = new Message();
        $messageEntity->setConversation($conversation);
        $messageEntity->setSenderType('contact');
        $messageEntity->setSenderId($contact->getId()); // ID контакта
        $messageEntity->setExternalId((string)$telegramMessage['message_id']);
        $messageEntity->setText($text);
        $messageEntity->setPayload($payload);
        $messageEntity->setIsRead(false);
        $messageEntity->setDirection('inbound');
        $messageEntity->setSentAt(new \DateTimeImmutable('@' . $telegramMessage['date']));
        $this->entityManager->persist($messageEntity);

        // Обновить conversation
        $conversation->setLastMessageAt(new \DateTimeImmutable());
        $conversation->setUnreadCount($conversation->getUnreadCount() + 1);

        // ... сохранение сообщения в БД ...
        $this->entityManager->flush();

        // Отправляем уведомление в Redis Pub/Sub
        $this->messageBus->dispatch(
            new NewMessageNotification([
                'conversationId' => $conversation->getId()->toString(),
                'messageId' => $messageEntity->getId()->toString(),
                'text' => $messageEntity->getText(),
                'senderName' => $contact->getMainName(),
                'type' => $messageEntity->getDirection() === 'inbound' ? 'incoming' : 'outgoing',
                'messengerType' => $conversation->getType(),
            ])
        );

        $this->logger->info('Processed incoming Telegram message from chat ' . $chatId);

        // TODO: Отправить уведомление через WebSocket Server
        // $this->messageBus->dispatch(new NewMessageNotification($messageEntity->getId()));
    }
}
