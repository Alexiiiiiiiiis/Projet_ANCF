<?php

namespace App\Repository;

use App\Entity\SystemParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemParameter>
 */
class SystemParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemParameter::class);
    }

    /** Cherche un paramètre système par sa clé. */
    public function findByKey(string $key): ?SystemParameter
    {
        return $this->findOneBy(['paramKey' => $key]);
    }

    /** Valeur d'un paramètre système, ou la valeur par défaut s'il n'existe pas. */
    public function getValue(string $key, string $default = ''): string
    {
        $param = $this->findByKey($key);

        return $param?->getParamValue() ?? $default;
    }
}
