<?php

namespace App\Repository;

use App\Entity\ConfigRecette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfigRecette>
 */
class ConfigRecetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfigRecette::class);
    }

    public function findOneByEntreprise(int $identreprise): ?ConfigRecette
    {
        return $this->findOneBy(['identreprise' => $identreprise, 'deletedAt' => null]);
    }
}
