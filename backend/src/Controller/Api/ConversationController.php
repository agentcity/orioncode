<?php

namespace App\Controller\Api;

use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/conversations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ConversationController extends AbstractController
{
    #[Route('', name: 'app_api_conversation', methods: ['GET'])]
    public function index(ConversationRepository $repository): JsonResponse
    {
        // Получаем беседы текущего пользователя
        $conversations = $repository->findBy(
            ['assignedTo' => $this->getUser()],
            ['lastMessageAt' => 'DESC']
        );

        $data = [];
        foreach ($conversations as $conv) {
            $data[] = [
                'id' => $conv->getId()->toString(),
                'type' => $conv->getType(),
                'status' => $conv->getStatus(),
                'unreadCount' => $conv->getUnreadCount(),
                'lastMessageAt' => $conv->getLastMessageAt()?->format(\DateTime::ATOM),
                'contact' => [
                    'id' => $conv->getContact()->getId()->toString(),
                    'mainName' => $conv->getContact()->getMainName(), // Убедитесь, что этот метод есть в Contact
                ]
            ];
        }

        return $this->json($data);
    }

    // 2. НОВЫЙ МЕТОД: Детали конкретной беседы
    #[Route('/{id}', name: 'app_api_conversation_show', methods: ['GET'])]
    public function show(Conversation $conversation): JsonResponse
    {
        // Symfony автоматически найдет объект Conversation по UUID из ссылки
        return $this->json([
            'id' => $conversation->getId()->toString(),
            'type' => $conversation->getType(),
            'status' => $conversation->getStatus(),
            'contact' => [
                'id' => $conversation->getContact()->getId()->toString(),
                'mainName' => $conversation->getContact()->getMainName(),
            ]
        ]);
    }

    #[Route('/{id}/read', name: 'api_conversation_read', methods: ['POST'])]
    public function markAsRead(Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        $conversation->setUnreadCount(0);
        $em->flush();

        return $this->json(['status' => 'success']);
    }

    #[Route('/api/conversations/internal', methods: ['POST'])]
    public function startInternalChat(Request $request, EntityManagerInterface $em, UserRepository $userRepo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $targetUser = $userRepo->find($data['userId']);
        $currentUser = $this->getUser();

        // Проверяем, нет ли уже такого чата
        $existing = $em->getRepository(Conversation::class)->findOneBy([
            'type' => 'internal',
            'assignedTo' => $currentUser,
            'targetUser' => $targetUser
        ]);

        if ($existing) return $this->json($existing, 200, [], ['groups' => 'chat']);

        $conversation = new Conversation();
        $conversation->setType('internal');
        $conversation->setAssignedTo($currentUser);
        $conversation->setTargetUser($targetUser);
        $conversation->setLastMessageAt(new \DateTimeImmutable());
        $conversation->setStatus('active');

        $em->persist($conversation);
        $em->flush();

        return $this->json($conversation, 201, [], ['groups' => 'chat']);
    }

}
