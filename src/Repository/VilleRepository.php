<?php

namespace App\Repository;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Gare;
use App\Entity\Ville;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ville>
 */
class VilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ville::class);
    }

    /**
     * Villes d'une entreprise ayant au moins une gare ACTIVE — pour la navigation client (mobile/web).
     *
     * @return Ville[]
     */
    public function findPourNavigation(int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin(Gare::class, 'g', 'WITH', 'g.ville = v AND g.deletedAt IS NULL AND g.statut = :actif')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('actif', ReferenceStatus::ACTIF->value)
            ->groupBy('v.id')
            ->orderBy('v.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
