<?php

namespace App\Controller\Api;

use App\Entity\Conversation;
use App\Repository\MessageRepository;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/api/conversations/{id}/messages')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MessageController extends AbstractController
{
    #[Route('', name: 'api_messages_list', methods: ['GET'])]
    public function index(Conversation $conversation, MessageRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        $userId = $user->getId()->toString();

        // 1. ПРОВЕРКА ДОСТУПА (как в ConversationController) 🚀
        $hasAccess = false;
        if ($conversation->getType() === 'orion') {
            if ($conversation->getAssignedTo() === $user || $conversation->getTargetUser() === $user) {
                $hasAccess = true;
            }
        } else {
            $org = $conversation->getAccount()?->getOrganization();
            if ($org) {
                foreach ($org->getUsers() as $orgUser) {
                    if ($orgUser->getId()->toString() === $userId) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
        }

        if (!$hasAccess) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        // 2. ЗАГРУЗКА ПОСЛЕДНИХ 20 СООБЩЕНИЙ 🚀
        // Берем последние по времени, но потом развернем для фронта
        $messages = $repository->findBy(
            ['conversation' => $conversation],
            ['sentAt' => 'DESC'], // Сначала последние
            20                    // Только 20 штук
        );

        // Разворачиваем, чтобы в чате они шли: [старое -> новое]
        $messages = array_reverse($messages);

        $data = array_map(function($m) use ($conversation) {
            $payload = $m->getPayload() ?? [];
            // Логика определения senderId для внутренних чатов
            if ($conversation->getType() === 'orion' && !isset($payload['senderId'])) {
                $payload['senderId'] = ($m->getDirection() === 'outbound')
                    ? $conversation->getAssignedTo()->getId()->toString()
                    : $conversation->getTargetUser()->getId()->toString();
            }

            return [
                'id' => $m->getId()->toString(),
                'text' => $m->getText(),
                'direction' => $m->getDirection(),
                'status' => $m->getStatus(),
                'sentAt' => $m->getSentAt()->format(\DateTime::ATOM),
                'payload' => $payload,
                'attachments' => array_map(fn($a) => [
                    'url' => $a->getUrl(),
                    'type' => $a->getType()
                ], $m->getAttachments()->toArray())
            ];
        }, $messages);

        return $this->json($data);
    }


    #[Route('', name: 'api_messages_send', methods: ['POST'])]
    public function send(
        Conversation $conversation,
        Request $request,
        ChatService $chatService
    ): JsonResponse {
        ini_set('memory_limit', '256M');
        $data = json_decode($request->getContent(), true);

        if (empty($data['text']) && empty($data['attachment'])) {
            return $this->json(['error' => 'Content is required'], 400);
        }

        try {
            $message = $chatService->processNewMessage($conversation, $this->getUser(), $data);

            return $this->json([
                'id' => $message->getId()->toString(),
                'text' => $message->getText(),
                'direction' => $message->getDirection(),
                'payload' => $message->getPayload(),
                'sentAt' => $message->getSentAt()->format(\DateTime::ATOM),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}

