<?php

namespace App\Repository;

use App\Entity\Approvisionnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Approvisionnement>
 */
class ApprovisionnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Approvisionnement::class);
    }

    // -- Statistiques -- //
    // Pilotées par le STATUT métier : un approvisionnement ANNULE (stock retiré) est exclu des coûts mais reste
    // visible pour l'audit ; la corbeille ('deletedAt') ne gère que la visibilité dans les listes.

    public function coutTotal(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('a')
            ->select('SUM(da.couttotal) AS total')
            ->join('a.detailapprovisionnements', 'da')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.dateappro >= :debut')
            ->andWhere('a.dateappro <= :fin')
            ->andWhere("a.statut != 'ANNULE'") // exclut les approvisionnements annulés des coûts
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    /** Achats par fournisseur (appros non annulés de la période) : nb d'appros + montant total. */
    public function achatsParFournisseur(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('a')
            ->select('f.id AS id, f.libelle AS libelle, COUNT(DISTINCT a.id) AS nbappros, COALESCE(SUM(da.couttotal), 0) AS montant')
            ->join('a.detailapprovisionnements', 'da')
            ->join('a.fournisseur', 'f')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.dateappro >= :debut')
            ->andWhere('a.dateappro <= :fin')
            ->andWhere("a.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('f.id')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function coutParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('a')
            ->select('DATE(a.dateappro) AS label, SUM(da.couttotal) AS montant')
            ->join('a.detailapprovisionnements', 'da')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.dateappro >= :debut')
            ->andWhere('a.dateappro <= :fin')
            ->andWhere("a.statut != 'ANNULE'") // exclut les approvisionnements annulés des coûts
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Approvisionnement[] Returns an array of Approvisionnement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Approvisionnement
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
