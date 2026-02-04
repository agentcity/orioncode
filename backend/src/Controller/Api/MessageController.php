<?php

namespace App\Controller\Api;

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
        $data = json_decode($request->getContent(), true);

        if (empty($data['text'])) {
            return $this->json(['error' => 'Text is required'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setText($data['text']);
        $message->setDirection('outgoing'); // Обязательно
        $message->setSenderType('user');    // Обязательно
        $message->setStatus('sent');        // Наш новый статус
        $message->setIsRead(true);
        $message->setSentAt(new \DateTimeImmutable());

        // Если в Entity Message свойство $id не инициализируется в конструкторе:
        // $message->setId(\Ramsey\Uuid\Uuid::uuid4());

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
            'status' => $message->getStatus(),
            'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
        ], 201);
    }
}
