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
     * Получить все беседы пользователя с подгрузкой контактов (оптимизация запроса)
     *
     * @param User $user
     * @return Conversation[]
     */
    public function findAllByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('contact') // Делаем JOIN, чтобы избежать проблемы N+1 при получении имен контактов
            ->leftJoin('c.contact', 'contact')
            ->where('c.assignedTo = :user')
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Поиск беседы по внешнему ID (например, ID из WhatsApp/Telegram)
     */
    public function findByExternalId(string $type, string $externalId): ?Conversation
    {
        return $this->findOneBy([
            'type' => $type,
            'externalId' => $externalId
        ]);
    }
}
