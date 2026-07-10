<?php

namespace App\Repository;

use App\Entity\Ligne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ligne>
 */
class LigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ligne::class);
    }

    // -- Statistiques (mirror de TrajetRepository, groupé sur la ligne) -- //

    public function countTotal(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.identreprise = :ide')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllAvecStats(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array {
        return $this->createQueryBuilder('l')
            ->select(
                'l.id',
                'l.libelle',
                'l.codeligne',
                'COUNT(DISTINCT v.id) AS nbvoyages',
                'COUNT(t.id) AS nbtickets',
                'COALESCE(SUM(t.prix), 0) AS recette',
            )
            ->leftJoin('l.voyages', 'v', 'WITH', 'v.datedepartprevue >= :debut AND v.datedepartprevue <= :fin AND v.deletedAt IS NULL')
            // Billets VALIDE seulement (exclut les désistés reportés/annulés) et HORS réservation (recette
            // réservation reconnue au paiement, ajoutée séparément par LigneStatsProvider) — anti double-comptage.
            ->leftJoin('v.tickets', 't', 'WITH', "t.createdAt >= :debut AND t.createdAt <= :fin AND t.statut = 'VALIDE' AND t.reservation IS NULL")
            ->andWhere('l.identreprise = :ide')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id') // l.libelle / l.codeligne sont fonctionnellement dépendants de la PK (MySQL 8)
            ->orderBy('recette', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
