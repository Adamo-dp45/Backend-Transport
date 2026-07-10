<?php

namespace App\Repository;

use App\Entity\ConfigRemise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfigRemise>
 */
class ConfigRemiseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigRemise::class);
    }

    public function findOneByEntreprise(int $identreprise): ?ConfigRemise
    {
        return $this->findOneBy(['identreprise' => $identreprise]);
    }
}
