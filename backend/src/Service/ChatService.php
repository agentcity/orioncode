<?php

namespace App\Service;

use App\Service\Messenger\MessengerFactory;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Organization\Entity\Organization;
use App\Repository\MessageRepository;
use App\Service\AI\AiModelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class ChatService
{
    private $em;    private $params;
    private $messageRepository;
    private $aiService;

    const AI_UUID = '00000000-0000-0000-0000-000000000000';

    public function __construct(
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        MessageRepository $messageRepository,
        AiModelInterface $aiService,
        private MessengerFactory $messengerFactory
    ) {
        $this->em = $em;
        $this->params = $params;
        $this->messageRepository = $messageRepository;
        $this->aiService = $aiService;
    }

    public function processNewMessage(Conversation $conversation, $user, array $data): Message
    {
        // 1. АВТО-ПРИВЯЗКА ОРГАНИЗАЦИИ (Денормализация 2026)
        // Привязываем организацию ТОЛЬКО если это внешний канал (ВК, ТГ, Авито)
        if ($conversation->getType() !== 'orion') {
            if (!$conversation->getOrganization() && $conversation->getAccount()) {
                $org = $conversation->getAccount()->getOrganization();
                if ($org) {
                    $conversation->setOrganization($org);
                    $this->em->persist($conversation);
                }
            }
        }
        // Если тип 'orion' — поле organization_id остается NULL.
        // Доступ к такому чату будет ВСЕГДА только у двух участников,
        // вне зависимости от того, в каких организациях они состоят.

        // 1. Создаем сообщение от человека
        $message = new Message();
        $message->setConversation($conversation);
        $message->setText($data['text'] ?? '');
        $message->setDirection('outgoing');
        $message->setSenderType('user');
        $message->setManager($user);    // Записываем в manager_id
        $message->setContact(null);      // Обнуляем contact_id
        $message->setStatus('sent');
        $message->setSentAt(new \DateTimeImmutable());
        $message->setIsRead(true);

        if (!empty($data['attachment'])) {
            $message->setPayload(['filePath' => $this->saveBase64File($data['attachment'])]);
        }

        $payload = $message->getPayload() ?? [];
        $payload['senderId'] = $user->getId()->toString();
        $payload['senderName'] = $user->getFirstName();
        $payload['senderLastName'] = $user->getLastName();
        $payload['fullName'] = trim($user->getFirstName() . ' ' . $user->getLastName());

        $message->setPayload($payload);



        // ЛОГИКА ЦИТИРОВАНИЯ:
        if (!empty($data['replyToId'])) {

            try {
                // Убеждаемся, что мы ищем по строке или UUID
                if (Uuid::isValid($data['replyToId'])) {
                    $parentMessage = $this->em->getRepository(Message::class)->find(Uuid::fromString($data['replyToId']));
                } else {
                    $parentMessage = $this->em->getRepository(Message::class)->find($data['replyToId']);
                }

                if ($parentMessage) {
                    // 1. Устанавливаем связь в БД (через поле reply_to_id)
                    $message->setReplyTo($parentMessage);

                    // 2. Безопасно обновляем payload
                    $currentPayload = $message->getPayload();
                    // Если payload в базе хранится как строка, декодируем её
                    $payload = is_string($currentPayload) ? json_decode($currentPayload, true) : ($currentPayload ?? []);

                    $payload['replyTo'] = [
                        'id'   => $parentMessage->getId()->toString(),
                        'text' => mb_substr($parentMessage->getText(), 0, 100) // Ограничим длину для легкости
                    ];

                    $message->setPayload($payload);
                }
            } catch (\Exception $e) {
                // Если ID битый или ошибка БД — просто логируем и идем дальше, не ломая отправку
                error_log("ReplyTo Error: " . $e->getMessage());
            }
        }




        $this->em->persist($message);
        $conversation->setLastMessageAt($message->getSentAt());
        $this->em->flush();

        // 1. Отправляем сообщение во внешний мессенджер
        if ($conversation->getType() !== 'orion') { // Если это не внутренний чат
            $this->sendToExternalMessenger($conversation, $message->getText());
        }


        // 2. Рассылаем сокетам сообщение пользователя
        $this->broadcastToRedis($conversation, $message);

        // 3. ПРОВЕРКА: Если пишем ИИ (по UUID)
        // ПРОВЕРКА ДЛЯ ИИ (ЗАЩИТА ОТ ЦИКЛА):
        // Если это сообщение от БОТА (senderType === 'bot'), НЕ ГЕНЕРИРУЕМ ответ!
        if ($message->getSenderType() !== 'bot') {
            $targetId = $this->resolveTargetId($conversation, $user);
            if ($targetId === self::AI_UUID) {
                $this->generateAiReply($conversation, $data['text'] ?? '');
            }
        }

        return $message;
    }

    public function generateAiReply(Conversation $conversation, string $userText)
    {
        $cost = 2.00;
        $organization = $conversation->getOrganization();
        $user = $conversation->getAssignedTo(); // Владелец личного чата

        // 🚀 1. ОПРЕДЕЛЯЕМ, КТО ПЛАТИТ: Организация или Юзер
        $payer = null;
        $payerName = "";

        if ($organization) {
            $payer = $organization;
            $payerName = "организации «" . $organization->getName() . "»";
        } else if ($conversation->getType() === 'orion' && $user) {
            $payer = $user;
            $payerName = "Ваш личный";
        }

        // 🚀 2. ПРОВЕРКА И СПИСАНИЕ (ОДИН БЛОК ДЛЯ ВСЕХ)
        if ($payer) {
            if ($payer->getBalance() < $cost) {
                $this->sendBotServiceMessage($conversation, "🤖 Внимание: $payerName баланс исчерпан. Ответы ИИ приостановлены. Пожалуйста, пополните счет.");
                return;
            }

            // Списываем средства
            $payer->setBalance($payer->getBalance() - $cost);
            $this->em->persist($payer);
            $this->em->flush();
        } else {
            // Если нет ни организации, ни юзера — ИИ молчит
            return;
        }


        // 1. Собираем историю сообщений для контекста
        $history = [];
        $rawMessages = $this->messageRepository->findBy(
            ['conversation' => $conversation],
            ['sentAt' => 'DESC'],
            31 // Берем 31, чтобы включая текущее было около 30
        );

        // Переворачиваем, чтобы было от старых к новым
        foreach (array_reverse($rawMessages) as $msg) {
            // Если отправитель пользователь — роль 'user', если ИИ — 'assistant'
            $payload = $msg->getPayload();
            $role = ($payload['senderId'] ?? null) === self::AI_UUID ? 'assistant' : 'user';

            $history[] = [
                'role' => $role,
                'content' => $msg->getText()
            ];
        }

        // 2. Передаем МАССИВ истории вместо одной строки
        $aiText = $this->aiService->ask($history);

        // 3. Сохраняем ответ ИИ
        $aiMsg = new Message();
        $aiMsg->setConversation($conversation);
        $aiMsg->setText($aiText);
        $aiMsg->setDirection('inbound');
        $aiMsg->setSenderType('bot');
        // Находим сущность бота в таблице users по его фиксированному UUID
        $botUser = $this->em->getRepository(\App\Entity\User::class)->find(self::AI_UUID);

        if ($botUser) {
            $aiMsg->setManager($botUser); // Записываем ID бота в manager_id
        }

        $aiMsg->setContact(null);

        $aiMsg->setStatus('delivered');
        $aiMsg->setSentAt(new \DateTimeImmutable());
        $aiMsg->setPayload(['senderId' => self::AI_UUID]);

        $this->em->persist($aiMsg);
        $this->em->flush();

        // Отправляем ответ ИИ в чат мессенджера внешней системы
        if ($conversation->getType() !== 'orion') {
            $this->sendToExternalMessenger($conversation, $aiText);
        }

        $this->broadcastToRedis($conversation, $aiMsg);
    }


    /**
     * 🚀 ВСПОМОГАТЕЛЬНЫЙ МЕТОД ДЛЯ УВЕДОМЛЕНИЙ БОТА
     */
    private function sendBotServiceMessage(Conversation $conversation, string $text): void
    {
        $aiMsg = new Message();
        $aiMsg->setConversation($conversation);
        $aiMsg->setText($text);
        $aiMsg->setDirection('inbound');
        $aiMsg->setSenderType('bot');
        $aiMsg->setSentAt(new \DateTimeImmutable());
        $aiMsg->setStatus('delivered');
        $aiMsg->setPayload(['senderId' => self::AI_UUID, 'service' => true]);

        $this->em->persist($aiMsg);
        $this->em->flush();
        $this->broadcastToRedis($conversation, $aiMsg);
    }

    private function broadcastToRedis(Conversation $conversation, Message $message)
    {
        try {
            $redis = RedisAdapter::createConnection($_ENV['REDIS_URL'] ?? 'redis://orion_redis:6379');
            // 🚀 БЕРЕМ ДАННЫЕ НАПРЯМУЮ ИЗ БЕСЕДЫ (Денормализация)
            $orgId = $conversation->getOrganization() ? $conversation->getOrganization()->getId()->toString() : null;

            // 🚀 ЗАЩИТА: Аккаунт может быть NULL для внутренних чатов (orion)
            $account = $conversation->getAccount();
            $userId = ($account && $account->getUser()) ? $account->getUser()->getId()->toString() : "0";

            $data = [
                'conversationId' => $conversation->getId()->toString(),
                'orgId' => $orgId,
                'userId' => $userId, // 🚀 Для одиночек
                'payload' => [
                    'id' => $message->getId()->toString(),
                    'text' => $message->getText(),
                    'direction' => $message->getDirection() === 'outgoing' ? 'outbound' : 'inbound',
                    'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
                    'payload' => $message->getPayload()
                ]
            ];
            $redis->publish('chat_messages', json_encode($data));
        } catch (\Exception $e) {
            error_log("REDIS ERROR: " . $e->getMessage());
        }
    }

    private function resolveTargetId(Conversation $c, $user): ?string
    {
        if ($c->getType() === 'orion') {
            $rec = ($c->getAssignedTo() === $user) ? $c->getTargetUser() : $c->getAssignedTo();
            return $rec ? $rec->getId()->toString() : null;
        }
        return $c->getContact() ? $c->getContact()->getId() : null;
    }

    // Добавь это тело метода в ChatService.php
    private function sendToExternalMessenger(Conversation $conversation, string $text): void
    {
        $type = $conversation->getType();
        $messenger = $this->messengerFactory->get($type);

        if ($messenger) {
            $account = $conversation->getAccount();
            $contact = $conversation->getContact();

            if (!$account || !$contact) return;

            $externalId = $contact->getExternalId();

            if ($externalId) {
                try {
                    // 🚀 ПРОСТО ПЕРЕДАЕМ АККАУНТ ЦЕЛИКОМ
                    $messenger->sendMessage($externalId, $text, $account);
                } catch (\Exception $e) {
                    error_log("EXTERNAL SEND ERROR ({$type}): " . $e->getMessage());
                }
            }
        }
    }


    private function saveBase64File(string $base64): string
    {
        $data = base64_decode(str_contains($base64, ',') ? explode(',', $base64)[1] : $base64);
        $fileName = bin2hex(random_bytes(10)) . '.jpg';
        $dir = $this->params->get('kernel.project_dir') . '/public/uploads/chat/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . $fileName, $data);
        return '/uploads/chat/' . $fileName;
    }
}
