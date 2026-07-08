<?php

namespace App\Repository;

use App\Entity\Depannage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depannage>
 */
class DepannageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depannage::class);
    }

    // -- Statistiques -- //
    // Pilotées par le STATUT métier (et non par 'deletedAt') : un dépannage ANNULE (stock restauré) est exclu
    // des coûts/compteurs mais reste visible pour l'audit ; la corbeille ne gère que la visibilité dans les listes.

    public function coutTotal(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('d')
            ->select('SUM(d.couttotal) AS total')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'") // exclut les dépannages annulés des coûts
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    /** Nombre de dépannages (hors annulés) sur la période — pour le coût moyen par panne. */
    public function countByPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Dépannages par type de panne (hors annulés) : fréquence + coût, sur la période. */
    public function parTypePanne(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('tp.libelle AS type, COUNT(d.id) AS nb, COALESCE(SUM(d.couttotal), 0) AS cout')
            ->join('d.typepanne', 'tp')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('tp.id')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Top pièces consommées via les dépannages (hors annulés) de la période : quantité + coût. */
    public function topPiecesConsommees(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, int $limit = 12): array
    {
        return $this->createQueryBuilder('d')
            ->select('p.id AS id, p.libelle AS libelle, SUM(dd.quantite) AS quantite, COALESCE(SUM(dd.quantite * dd.prixunitaire), 0) AS cout')
            ->join('d.detaildepannages', 'dd')
            ->join('dd.piece', 'p')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('p.id')
            ->orderBy('quantite', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function coutParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('DATE(d.datedepannage) AS label, SUM(d.couttotal) AS montant')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'") // exclut les dépannages annulés des coûts
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Flotte -- //
    public function countParVehicule(int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('c.matricule, COUNT(d.id) AS nbrdepannages')
            ->join('d.car', 'c')
            ->andWhere('d.identreprise = :ide')
            ->andWhere("d.statut != 'ANNULE'") // exclut les dépannages annulés
            ->setParameter('ide', $identreprise)
            ->groupBy('c.matricule')
            ->orderBy('nbrdepannages', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();
    }

    public function coutParVehicule(int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('c.matricule, SUM(d.couttotal) AS couttotal')
            ->join('d.car', 'c')
            ->andWhere('d.identreprise = :ide')
            ->andWhere("d.statut != 'ANNULE'") // exclut les dépannages annulés
            ->setParameter('ide', $identreprise)
            ->groupBy('c.matricule')
            ->orderBy('couttotal', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();
    }

    // -- FlotteActivity -- //

    public function countParCar(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('IDENTITY(d.car) AS carid, COUNT(d.id) AS nbdepannages')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->andWhere("d.statut != 'ANNULE'") // exclut les dépannages annulés
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('d.car')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Depannage[] Returns an array of Depannage objects
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

    //    public function findOneBySomeField($value): ?Depannage
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
