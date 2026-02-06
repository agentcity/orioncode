<?php

namespace App\Controller\Api;

use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Ramsey\Uuid\Uuid;

#[Route('/api/conversations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ConversationController extends AbstractController
{
    #[Route('', name: 'api_conversations_list', methods: ['GET'])]
    public function index(ConversationRepository $repository, \Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $userId = $user->getId()->toString();

        // Используем чистый SQL-подобный подход через QueryBuilder, чтобы точно найти всё
        $qb = $repository->createQueryBuilder('c');
        $conversations = $qb->where($qb->expr()->orX(
            $qb->expr()->eq('c.assignedTo', ':user'),
            $qb->expr()->eq('c.targetUser', ':user')
        ))
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function($c) use ($user) {
            // Определяем, кто наш собеседник для отображения имени
            $contactName = 'Неизвестно';

            if ($c->getType() === 'internal') {
                $recipient = ($c->getAssignedTo() === $user) ? $c->getTargetUser() : $c->getAssignedTo();
                if ($recipient) {
                    $contactName = $recipient->getFirstName() . ' ' . $recipient->getLastName();
                }
            } else {
                $contactName = $c->getContact() ? $c->getContact()->getMainName() : 'Внешний контакт';
            }

            return [
                'id' => $c->getId()->toString(),
                'type' => $c->getType() ?? 'chat',
                'status' => $c->getStatus(),
                'lastMessageAt' => $c->getLastMessageAt() ? $c->getLastMessageAt()->format(\DateTime::ATOM) : null,
                'unreadCount' => $c->getUnreadCount(),
                'contact' => [
                    'id' => $c->getContact()?->getId(),
                    'mainName' => $contactName,
                ]
            ];
        }, $conversations);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_conversations_show', methods: ['GET'])]
    public function show(Conversation $conversation): JsonResponse
    {
        $user = $this->getUser();

        // Проверка доступа
        if ($conversation->getAssignedTo() !== $user && $conversation->getTargetUser() !== $user) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        // Определяем имя для шапки чата
        if ($conversation->getType() === 'internal') {
            $recipient = ($conversation->getAssignedTo() === $user) ? $conversation->getTargetUser() : $conversation->getAssignedTo();
            $contactName = $recipient ? ($recipient->getFirstName() . ' ' . $recipient->getLastName()) : 'Коллега';
        } else {
            $contactName = $conversation->getContact() ? $conversation->getContact()->getMainName() : 'Клиент';
        }

        return $this->json([
            'id' => $conversation->getId()->toString(),
            'type' => $conversation->getType(),
            'contact' => [
                'mainName' => $contactName,
            ]
        ]);
    }

    #[Route('/internal', name: 'api_conversations_create_internal', methods: ['POST'], priority: 2)]
    public function createInternal(Request $request, UserRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $targetUserId = $data['userId'] ?? null;
            $currentUser = $this->getUser();

            if (!$targetUserId) {
                return $this->json(['error' => 'User ID is required'], 400);
            }

            $targetUser = $userRepo->find($targetUserId);
            if (!$targetUser) {
                return $this->json(['error' => 'User not found'], 404);
            }

            // Поиск существующего чата
            $existing = $em->getRepository(Conversation::class)->createQueryBuilder('c')
                ->where('c.type = :type')
                ->andWhere('(c.assignedTo = :u1 AND c.targetUser = :u2) OR (c.assignedTo = :u2 AND c.targetUser = :u1)')
                ->setParameter('type', 'internal')
                ->setParameter('u1', $currentUser)
                ->setParameter('u2', $targetUser)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                return $this->json($existing, 200, [], ['groups' => 'chat']);
            }

            $conversation = new Conversation();

            // Силовая установка ID
            $reflection = new \ReflectionProperty(Conversation::class, 'id');
            $reflection->setAccessible(true);
            $reflection->setValue($conversation, Uuid::uuid4());

            $conversation->setType('internal');
            $conversation->setAssignedTo($currentUser);
            $conversation->setTargetUser($targetUser);

            // КРИТИЧЕСКИЙ МОМЕНТ: Поле Account
            // Если у пользователя нет аккаунта, берем первый попавшийся из базы для теста
            if (method_exists($currentUser, 'getAccount') && $currentUser->getAccount()) {
                $conversation->setAccount($currentUser->getAccount());
            } else {
                // Если аккаунт не найден у юзера, пробуем найти хоть какой-то в системе
                $account = $em->getRepository(\App\Entity\Account::class)->findOneBy([]);
                if ($account) {
                    $conversation->setAccount($account);
                } else {
                    throw new \Exception("В базе данных нет ни одного Account. Создайте его!");
                }
            }

            $conversation->setLastMessageAt(new \DateTimeImmutable());
            $conversation->setStatus('active');
            $conversation->setUnreadCount(0);

            $em->persist($conversation);
            $em->flush();

            return $this->json($conversation, 201, [], ['groups' => 'chat']);

        } catch (\Exception $e) {
            // Если произойдет ошибка, ты увидишь её в браузере вместо просто "500"
            return $this->json([
                'error' => 'Backend Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    #[Route('/{id}/read', name: 'api_conversations_read', methods: ['POST'])]
    public function markAsRead(Conversation $conversation, EntityManagerInterface $em): JsonResponse
    {
        if ($conversation->getAssignedTo() !== $this->getUser() && $conversation->getTargetUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        $conversation->setUnreadCount(0);
        $em->flush();

        return $this->json(['status' => 'success']);
    }
}
