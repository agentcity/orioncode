<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 *
 * @method Contact|null find($id, $lockMode = null, $lockVersion = null)
 * @method Contact|null findOneBy(array $criteria, array $orderBy = null)
 * @method Contact[]    findAll()
 * @method Contact[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Поиск контакта по ID мессенджера (например, Telegram ID) внутри конкретного аккаунта
     */
    public function findByExternalId(string $externalId, string $source, Account $account): ?Contact
    {
        return $this->findOneBy([
            'externalId' => $externalId,
            'source' => $source,
            'account' => $account
        ]);
    }

    /**
     * Получить всех клиентов конкретного бизнеса (Атаманский Двор и т.д.)
     */
    public function findByAccount(Account $account): array
    {
        return $this->findBy(['account' => $account], ['createdAt' => 'DESC']);
    }

    /**
     * Поиск по имени (для живого поиска в приложении)
     */
    public function searchByName(string $query, Account $account): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.account = :account')
            ->andWhere('LOWER(c.mainName) LIKE LOWER(:query)')
            ->setParameter('account', $account)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.mainName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
