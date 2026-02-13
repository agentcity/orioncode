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
        return $this->createQueryBuilder('c')
            // Оптимизация: подгружаем данные аккаунта и контакта одним запросом (нет N+1)
            ->addSelect('a', 'contact', 'org')
            ->innerJoin('c.account', 'a')
            ->innerJoin('c.contact', 'contact')
            ->leftJoin('a.organization', 'org')
            ->leftJoin('org.users', 'org_user')
            // Условие доступа:
            ->where('a.user = :user') // Личный аккаунт (используем поле user, как договорились)
            ->orWhere('org_user.id = :userId') // Аккаунт организации
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId())
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
