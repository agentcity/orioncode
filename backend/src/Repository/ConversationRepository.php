<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * ГЛАВНЫЙ МЕТОД: Находит все беседы, доступные пользователю.
     * 1. Где он является владельцем (owner) аккаунта.
     * 2. Где он состоит в организации, которой принадлежит аккаунт.
     */
    public function findAvailableConversations(User $user): array
    {
        $qb = $this->createQueryBuilder('c');
        $userId = $user->getId();

        return $qb
            ->addSelect('a', 'contact', 'org')
            ->leftJoin('c.account', 'a')
            ->leftJoin('c.contact', 'contact')
            ->leftJoin('a.organization', 'org')
            // 🚀 Мы ПРИНУДИТЕЛЬНО джойним юзеров организации
            // Если юзер удален из организации, этот join вернет NULL
            ->leftJoin('org.users', 'ou', 'WITH', 'ou.id = :userId')
            ->where(
                $qb->expr()->orX(
                // 1. Внутренние чаты (всегда доступны участникам)
                    'c.assignedTo = :user',
                    'c.targetUser = :user',
                    // 2. Внешние чаты - ТОЛЬКО если юзер ЕСТЬ в этой организации прямо сейчас
                    $qb->expr()->andX(
                        'org.id IS NOT NULL',
                        'ou.id IS NOT NULL'
                    ),

                )
            )
            ->setParameter('user', $user)
            ->setParameter('userId', $userId)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }





    /**
     * Поиск беседы по типу и внешнему ID (для вебхуков)
     */
    public function findByExternalId(string $type, string $externalId): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->where('c.type = :type')
            ->andWhere('c.externalId = :externalId')
            ->setParameter('type', $type)
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
