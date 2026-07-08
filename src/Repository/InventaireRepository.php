<?php

namespace App\Repository;

use App\Entity\Inventaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventaire>
 */
class InventaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventaire::class);
    }

    // -- Statistiques -- //

    public function stockActuelParPiece(int $identreprise): array
    {
        return $this->createQueryBuilder('i')
            ->select('IDENTITY(i.piece) AS pieceid, SUM(
                CASE WHEN i.typemouvement = :entree THEN i.quantite
                    WHEN i.typemouvement = :sortie THEN -i.quantite
                    ELSE i.quantite
                END
            ) AS mouvement')
            ->andWhere('i.identreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->setParameter('entree', 'ENTREE')
            ->setParameter('sortie', 'SORTIE')
            ->groupBy('i.piece')
            ->getQuery()
            ->getArrayResult();
    }

    public function mouvementsRecents(int $identreprise, int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('p.libelle AS piece, i.typemouvement, i.quantite, i.datemouvement AS date')
            ->join('i.piece', 'p')
            ->andWhere('i.identreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->orderBy('i.datemouvement', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Inventaire[] Returns an array of Inventaire objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Inventaire
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
