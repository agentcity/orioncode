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
use Symfony\Component\Cache\Adapter\RedisAdapter;

#[Route('/api/conversations')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ConversationController extends AbstractController
{
    #[Route('', name: 'api_conversations_list', methods: ['GET'])]
    public function index(ConversationRepository $repository, \Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        try {
            $redisUrl = $_ENV['REDIS_URL'] ?? 'redis://orion_redis:6379';
            $redis = RedisAdapter::createConnection($redisUrl);
        } catch (\Exception $e) {
            $redis = null;
        }

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

        $data = array_map(function($c) use ($user, $redis) {
            // Определяем, кто наш собеседник для отображения имени
            $contactName = 'Неизвестно';

            if ($c->getType() === 'orion') {
                $recipient = ($c->getAssignedTo() === $user) ? $c->getTargetUser() : $c->getAssignedTo();
                if ($recipient) {
                    $contactName = $recipient->getFirstName() . ' ' . $recipient->getLastName();
                    $targetId = $recipient->getId()->toString();
                }
            } else {
                $contactName = $c->getContact() ? $c->getContact()->getMainName() : 'Внешний контакт';
                $targetId = $c->getContact()?->getId();
            }

            // 2. Проверяем статус в Redis
            $isOnline = false;
            $lastSeen = null;

            if ($redis && $targetId) {
                try {
                    // Устанавливаем таймаут, чтобы скрипт не висел, если Redis тупит
                    $status = $redis->get("user:status:{$targetId}");
                    $isOnline = ($status === 'online');
                    $lastSeen = $redis->get("user:lastSeen:{$targetId}");
                } catch (\Exception $e) {
                    // Если Redis упал, просто ставим false и идем дальше
                    $isOnline = false;
                }
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
                    'isOnline' => $isOnline,
                    'lastSeen' => $lastSeen,
                ]
            ];
        }, $conversations);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'api_conversations_show', methods: ['GET'])]
    public function show(Conversation $conversation): JsonResponse
    {
        $user = $this->getUser();
        try {
            $redisUrl = $_ENV['REDIS_URL'] ?? 'redis://orion_redis:6379';
            $redis = RedisAdapter::createConnection($redisUrl);
        } catch (\Exception $e) {
            $redis = null;
        }

        // Проверка доступа
        if ($conversation->getAssignedTo() !== $user && $conversation->getTargetUser() !== $user) {
            return $this->json(['error' => 'Access Denied'], 403);
        }

        // Определяем имя для шапки чата
        $targetId = null; // Инициализируем

        if ($conversation->getType() === 'orion') {
            $recipient = ($conversation->getAssignedTo() === $user) ? $conversation->getTargetUser() : $conversation->getAssignedTo();
            if ($recipient) {
                $contactName = $recipient->getFirstName() . ' ' . $recipient->getLastName();
                $targetId = $recipient->getId()->toString(); // БЕРЕМ ID ИЗ RECIPIENT
            } else {
                $contactName = 'Коллега';
            }
        } else {
            $contact = $conversation->getContact();
            $contactName = $contact ? $contact->getMainName() : 'Клиент';
            $targetId = $contact ? $contact->getId() : null; // БЕРЕМ ID ИЗ CONTACT
        }

        // 2. Проверяем статус в Redis
        $isOnline = false;
        $lastSeen = null;

        if ($redis && $targetId) {
            try {
                // Устанавливаем таймаут, чтобы скрипт не висел, если Redis тупит
                $status = $redis->get("user:status:{$targetId}");
                $isOnline = ($status === 'online');
                $lastSeen = $redis->get("user:lastSeen:{$targetId}");
            } catch (\Exception $e) {
                // Если Redis упал, просто ставим false и идем дальше
                $isOnline = false;
            }
        }

        return $this->json([
            'id' => $conversation->getId()->toString(),
            'type' => $conversation->getType(),
            'contact' => [
                'id' => $targetId,
                'mainName' => $contactName,
                'isOnline' => $isOnline,
                'lastSeen' => $lastSeen,
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
                ->setParameter('type', 'orion')
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

            $conversation->setType('orion');
            $conversation->setAssignedTo($currentUser);
            $conversation->setTargetUser($targetUser);

            // КРИТИЧЕСКИЙ МОМЕНТ: Поле Account
            // Если аккаунт есть — ставим, если нет — идем дальше.
            if (method_exists($conversation, 'setAccount')) {
                if (method_exists($currentUser, 'getAccount') && $currentUser->getAccount()) {
                    $conversation->setAccount($currentUser->getAccount());
                } else {
                    $account = $em->getRepository(\App\Entity\Account::class)->findOneBy([]);
                    if ($account) {
                        $conversation->setAccount($account);
                    }
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
