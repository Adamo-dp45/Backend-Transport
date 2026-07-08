<?php

namespace App\Repository;

use App\Entity\Detailcourrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Detailcourrier>
 */
class DetailcourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detailcourrier::class);
    }

    /** Colis groupés par tranche de valeur (grille tarifaire courrier), sur la période (courrier non annulé). */
    public function parTrancheValeur(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('dc')
            ->select('tc.id AS id, tc.libelle AS libelle, tc.valeurmin AS valeurmin, tc.valeurmax AS valeurmax, COUNT(dc.id) AS nb, COALESCE(SUM(dc.montant), 0) AS recette')
            ->join('dc.tarifcourrier', 'tc')
            ->join('dc.courrier', 'c')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('tc.id')
            ->orderBy('tc.valeurmin', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Colis perdus et total sur la période (courrier non annulé), pour suivre la perte AU NIVEAU DU COLIS
     * (un colis peut être perdu sans que tout le courrier le soit).
     *
     * @return array{perdus: int, total: int}
     */
    public function comptePertes(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('dc')
            ->select(
                "SUM(CASE WHEN dc.statut = 'PERDU' THEN 1 ELSE 0 END) AS perdus",
                'COUNT(dc.id) AS total'
            )
            ->join('dc.courrier', 'c')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return ['perdus' => (int)($row['perdus'] ?? 0), 'total' => (int)($row['total'] ?? 0)];
    }

    //    /**
    //     * @return Detailcourrier[] Returns an array of Detailcourrier objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Detailcourrier
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
