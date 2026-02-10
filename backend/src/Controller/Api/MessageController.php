<?php

namespace App\Controller\Api;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Message\SendMessage;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Cache\Adapter\RedisAdapter;

#[Route('/api/conversations/{id}/messages')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessageController extends AbstractController
{
    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function index(Conversation $conversation, MessageRepository $repository): JsonResponse
    {
        $user = $this->getUser();

        // РАЗРЕШАЕМ ДОСТУП ДЛЯ ОБОИХ УЧАСТНИКОВ
        if ($conversation->getAssignedTo() !== $user && $conversation->getTargetUser() !== $user) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        $messages = $repository->findBy(
            ['conversation' => $conversation],
            ['sentAt' => 'ASC']
        );


        $data = array_map(function($m) use ($conversation) {
            $payload = $m->getPayload() ?? [];

            // Если это внутренний чат и в базе нет senderId,
            // пробуем определить его по направлению (только для истории)
            if ($conversation->getType() === 'orion' && !isset($payload['senderId'])) {
                if ($m->getDirection() === 'outbound') {
                    $payload['senderId'] = $conversation->getAssignedTo()->getId()->toString();
                } else {
                    $payload['senderId'] = $conversation->getTargetUser()->getId()->toString();
                }
            }

            return [
                'id' => $m->getId()->toString(),
                'text' => $m->getText(),
                'direction' => $m->getDirection(),
                'status' => $m->getStatus(),
                'senderType' => $m->getSenderType(),
                'isRead' => $m->isRead(),
                'sentAt' => $m->getSentAt()->format(\DateTime::ATOM),
                'payload' => $payload, // Теперь тут точно есть senderId
                'attachments' => array_map(function($a) {
                    return [
                        'id' => $a->getId()->toString(),
                        'type' => $a->getType(),
                        'url' => $a->getUrl(),
                        'fileName' => $a->getFileName(),
                        'mimeType' => $a->getMimeType()
                    ];
                }, $m->getAttachments()->toArray())
            ];
        }, $messages);

        return $this->json($data);
    }

    #[Route('', name: 'api_messages_send', methods: ['POST'])]
    public function send(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
        MessageRepository $repository
    ): JsonResponse {
        // Увеличиваем лимит памяти для обработки фото
        ini_set('memory_limit', '256M');

        $data = json_decode($request->getContent(), true);

        if (empty($data['text'])) {
            return $this->json(['error' => 'Text is required'], 400);
        }
        try {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setText($data['text'] ?? '');
            $message->setDirection('outgoing'); // Обязательно
            $message->setSenderType('user');    // Обязательно
            $message->setStatus('sent');        // Наш новый статус
            $message->setIsRead(true);
            $message->setSentAt(new \DateTimeImmutable());

            // Если в Entity Message свойство $id не инициализируется в конструкторе:
            // $message->setId(\Ramsey\Uuid\Uuid::uuid4());

            if (!empty($data['attachment'])) {
                $base64String = $data['attachment'];
                // Убираем заголовок base64, если он есть
                if (str_contains($base64String, ',')) {
                    $base64String = explode(',', $base64String)[1];
                }

                $imageData = base64_decode($base64String);
                if (!$imageData) throw new \Exception('Invalid base64 data');

                $fileName = bin2hex(random_bytes(10)) . '.jpg';
                $publicPath = '/uploads/chat/' . $fileName;
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/chat/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                file_put_contents($uploadDir . $fileName, $imageData);
                $message->setPayload(['filePath' => $publicPath]);
            }

            $em->persist($message);

            // Помечаем старые сообщения как отвеченные
            $incoming = $repository->findBy(['conversation' => $conversation, 'direction' => 'incoming', 'isRead' => false]);
            foreach ($incoming as $inc) {
                $inc->setStatus('replied');
                $inc->setIsRead(true);
            }

            $conversation->setUnreadCount(0);
            $conversation->setLastMessageAt($message->getSentAt());

            $payload = $message->getPayload() ?? [];
            $payload['senderId'] = $this->getUser()->getId()->toString(); // Твой ID
            $message->setPayload($payload);

            $em->flush(); // КРИТИЧНО: без этого ничего не запишется

            // ПУБЛИКАЦИЯ В REDIS (для Socket.io)
            try {
                // Создаем чистое соединение с Redis
                $redis = \Symfony\Component\Cache\Adapter\RedisAdapter::createConnection('redis://orion_redis:6379');

                $messageData = [
                    'id' => $message->getId()->toString(),
                    'text' => $message->getText(),
                    'direction' => $message->getDirection(),
                    'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
                    'payload' => [
                        'senderId' => $this->getUser()->getId()->toString()
                    ]
                ];

                $payload = $message->getPayload() ?: [];
                $payload['senderId'] = $this->getUser()->getId()->toString();

                // ВАЖНО: Если это фото, filePath уже должен быть в $payload после сохранения файла
                $redisData = [
                    'conversationId' => $conversation->getId()->toString(),
                    'payload' => [
                        'id' => $message->getId()->toString(),
                        'text' => $message->getText(),
                        'direction' => 'inbound', // Для получателя это входящее
                        'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
                        'payload' => $payload // Здесь ДОЛЖЕН быть filePath
                    ]
                ];

                // Публикуем. Важно: канал "chat_messages"
                $redis->publish('chat_messages', json_encode($redisData));

            } catch (\Exception $e) {
                // Если здесь будет ошибка, она запишется в логи докера
                error_log("CRITICAL REDIS ERROR: " . $e->getMessage());
            }

            // Отправка в Redis через Messenger
            $bus->dispatch(new \App\Message\SendMessage($message->getId()->toString()));

            return $this->json([
                'id' => $message->getId()->toString(),
                'text' => $message->getText(),
                'direction' => $message->getDirection(),
                'payload' => $message->getPayload(),
                'status' => $message->getStatus(),
                'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
