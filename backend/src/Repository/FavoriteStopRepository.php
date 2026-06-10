<?php

namespace App\Repository;

use App\Entity\FavoriteStop;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavoriteStop>
 */
class FavoriteStopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteStop::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.addedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndStop(User $user, string $stopId): ?FavoriteStop
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.stopId = :stopId')
            ->setParameter('user', $user)
            ->setParameter('stopId', $stopId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getMaxSortOrderForUser(User $user): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.sortOrder)')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}
