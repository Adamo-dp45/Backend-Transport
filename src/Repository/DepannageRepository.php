<?php

namespace App\Repository;

use App\Domain\Enum\DepannageStatus;
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

    /**
     * Agrégats des PIÈCES pour un lot de dépannages : nombre de lignes + quantité totale, en UNE
     * requête (COUNT + SUM groupés). La liste des dépannages affiche ces deux nombres ; sans cela il
     * fallait sérialiser la collection 'detaildepannages' entière et la sommer côté client.
     * Jointure interne : un dépannage sans pièce n'apparaît pas dans le résultat (le provider met 0).
     *
     * @param int[] $depannageIds
     * @return array<int, array{nombre:int, quantite:int}> map depannageId => agrégats
     */
    public function piecesParDepannageIds(array $depannageIds): array
    {
        if (empty($depannageIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('d.id AS did', 'COUNT(dd.id) AS nombre', 'COALESCE(SUM(dd.quantite), 0) AS quantite')
            ->join('d.detaildepannages', 'dd')
            ->andWhere('d.id IN (:ids)')
            ->groupBy('d.id')
            ->setParameter('ids', $depannageIds)
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['did']] = [
                'nombre' => (int) $row['nombre'],
                'quantite' => (int) $row['quantite'],
            ];
        }

        return $map;
    }

    // -- Statistiques -- //
    // DEUX EXCLUSIONS, ET ELLES NE DISENT PAS LA MÊME CHOSE :
    //  - 'statut != ANNULE' : l'opération a EXISTÉ puis été DÉFAITE. L'annulation est un acte tracé qui
    //    rend le stock à son état d'avant ; la ligne reste VISIBLE pour l'audit, hors des coûts.
    //  - 'deletedAt IS NULL' : la ligne n'aurait jamais dû être saisie. Ajouté le 28/09/2026, par
    //    symétrie avec les recettes — un total ne doit pas contenir ce qu'aucun écran ne montre.
    //
    // Les deux tiennent ENSEMBLE grâce au garde de 'SoftDeleteProcessor' : depuis la même date, une
    // ligne encore valide ne PEUT PLUS partir à la corbeille sans être annulée d'abord. Sans ce garde,
    // ce filtre-ci ferait disparaître le coût en laissant le stock entré — des pièces gratuites.

    public function coutTotal(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('d')
            ->select('SUM(d.couttotal) AS total')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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
            ->andWhere('d.deletedAt IS NULL')
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

    // -- Alertes -- //

    /**
     * Dépannages EN COURS ouverts depuis plus longtemps que $limite (datedepannage <= $limite) :
     * immobilisation qui traîne. Le car est hydraté (matricule pour le message d'alerte).
     *
     * @return Depannage[]
     */
    public function findOuvertsAnterieursA(int $identreprise, \DateTimeImmutable $limite): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.car', 'c')->addSelect('c')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.deletedAt IS NULL')
            ->andWhere('d.statut = :encours')
            ->andWhere('d.datedepannage <= :limite')
            ->setParameter('ide', $identreprise)
            ->setParameter('encours', DepannageStatus::EN_COURS->value)
            ->setParameter('limite', $limite)
            ->getQuery()
            ->getResult();
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
