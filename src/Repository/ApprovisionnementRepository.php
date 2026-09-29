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
        $row = $this->createQueryBuilder('a')
            ->select('SUM(a.couttotal) AS total')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
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
            ->select('f.id AS id, f.libelle AS libelle, COUNT(DISTINCT a.id) AS nbappros, COALESCE(SUM(a.couttotal), 0) AS montant')
            ->join('a.fournisseur', 'f')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
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
            ->select('DATE(a.dateappro) AS label, SUM(a.couttotal) AS montant')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
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
