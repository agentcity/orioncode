<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Найти аккаунт по токену (внутри JSON-поля credentials)
     *
     * @param string $token
     * @return Account|null
     */
    public function findOneByToken(string $token): ?Account
    {
        // Получаем все аккаунты с нужным типом мессенджера
        $qb = $this->createQueryBuilder('a')
            ->where('a.type = :type')
            ->setParameter('type', 'telegram');

        // В зависимости от того, хранится ли токен в виде:
        // ["token" => "abc123"] в JSON-поле `credentials`
        return $qb->andWhere('JSON_EXTRACT(a.credentials, :key) = :value')
            ->setParameter('key', '$.token')
            ->setParameter('value', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
