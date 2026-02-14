<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Conversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findByConversation(Conversation $conversation, int $limit = 50): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByConversationWithAttachments(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('a') // Выбираем вложения сразу
            ->leftJoin('m.attachments', 'a')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.sentAt', 'ASC')
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


}
