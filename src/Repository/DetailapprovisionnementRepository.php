<?php

namespace App\Repository;

use App\Entity\Detailapprovisionnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Detailapprovisionnement>
 */
class DetailapprovisionnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detailapprovisionnement::class);
    }

    /**
     * Somme des lignes d'un approvisionnement — la SOURCE du recalcul de
     * `Approvisionnement::$couttotal`.
     *
     * Lue en base et non depuis la collection en mémoire : après une réconciliation, la collection de
     * l'entité peut être partiellement chargée ou porter des lignes déjà détachées, et le total
     * recomposé serait faux sans erreur visible. Même patron que
     * `DetailmaindoeuvreRepository::totalPourDepannage()`.
     */
    public function totalPourApprovisionnement(int $approvisionnementId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.couttotal), 0)')
            ->andWhere('d.approvisionnement = :appro')
            ->setParameter('appro', $approvisionnementId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
