<?php

namespace App\Repository;

use App\Entity\ParametreReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParametreReservation>
 */
class ParametreReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametreReservation::class);
    }

    public function findOneByEntreprise(int $identreprise): ?ParametreReservation
    {
        return $this->findOneBy(['identreprise' => $identreprise]);
    }
}
