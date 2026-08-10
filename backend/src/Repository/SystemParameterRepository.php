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

    public function findByKey(string $key): ?SystemParameter
    {
        return $this->findOneBy(['paramKey' => $key]);
    }

    public function getValue(string $key, string $default = ''): string
    {
        $param = $this->findByKey($key);

        return $param?->getParamValue() ?? $default;
    }
}
