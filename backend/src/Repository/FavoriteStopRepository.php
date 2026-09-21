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

    /** Favoris d'un utilisateur, triés par position puis par date d'ajout. */
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

    /** Cherche si l'utilisateur a déjà cet arrêt ou cette ligne en favori. */
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

    /** Plus grande position de favori de l'utilisateur, pour placer le suivant en fin de liste. */
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
