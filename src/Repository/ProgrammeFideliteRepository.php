<?php

namespace App\Repository;

use App\Entity\ProgrammeFidelite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProgrammeFidelite>
 */
class ProgrammeFideliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgrammeFidelite::class);
    }

    public function findOneByEntreprise(int $identreprise): ?ProgrammeFidelite
    {
        return $this->findOneBy(['identreprise' => $identreprise]);
    }
}
