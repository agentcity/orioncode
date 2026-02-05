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


#[Route('/api/conversations/{id}/messages')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessageController extends AbstractController
{
    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function index(Conversation $conversation, MessageRepository $repository): JsonResponse
    {
        // Проверка: принадлежит ли чат текущему пользователю
        if ($conversation->getAssignedTo() !== $this->getUser()) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        $messages = $repository->findBy(
            ['conversation' => $conversation],
            ['sentAt' => 'ASC']
        );

        $data = array_map(fn($m) => [
            'id' => $m->getId()->toString(),
            'text' => $m->getText(),
            'direction' => $m->getDirection(),
            'payload' => $m->getPayload(),
            'status' => $m->getStatus(),
            'senderType' => $m->getSenderType(),
            'sentAt' => $m->getSentAt()->format(\DateTime::ATOM),
            'isRead' => $m->isRead(),
            'attachments' => array_map(fn($a) => [
                'id' => $a->getId()->toString(),
                'type' => $a->getType(),
                'url' => $a->getUrl(),
                'fileName' => $a->getFileName(),
                'mimeType' => $a->getMimeType()
            ], $m->getAttachments()->toArray())
        ], $messages);

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

            $em->flush(); // КРИТИЧНО: без этого ничего не запишется

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
