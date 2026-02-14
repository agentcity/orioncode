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

        return $qb
            ->addSelect('contact', 'a') // Предзагружаем для скорости отрисовки
            ->leftJoin('c.contact', 'contact')
            ->leftJoin('c.account', 'a')
            ->leftJoin('c.organization', 'org')
            ->leftJoin('org.users', 'u', 'WITH', 'u.id = :userId')
            ->where($qb->expr()->orX(
                'c.assignedTo = :user',   // Личные внутренние чаты
                'c.targetUser = :user',   // Личные внутренние чаты
                'u.id = :userId'          // ЧАТЫ ОРГАНИЗАЦИИ (теперь связь прямая!)
            ))
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId())
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }


    public function findLastMessages(string $conversationId, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conversationId')
            ->setParameter('conversationId', $conversationId)
            // 🚀 Сначала берем самые последние по ID или дате
            ->orderBy('m.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        // В контроллере мы их перевернем (array_reverse),
        // чтобы в чате они шли от старых к новым.
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
