<?php

namespace App\Repository;

use App\Entity\Maintenance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Maintenance>
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    /** L'unique ligne de configuration maintenance (ou null si pas encore créée). */
    public function getSingleton(): ?Maintenance
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
