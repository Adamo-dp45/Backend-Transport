<?php

namespace App\Repository;

use App\Entity\Piece;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Piece>
 */
class PieceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Piece::class);
    }

    public function stockParPiece(int $identreprise): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.libelle, p.stockinitial, p.seuilstock')
            ->andWhere('p.identreprise = :ide')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Valeur du stock (Σ stock × prix unitaire) + unités + nb pièces — snapshot courant. */
    public function valeurStock(int $identreprise): array
    {
        return $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.stockinitial * p.prixunitaire), 0) AS valeur, COALESCE(SUM(p.stockinitial), 0) AS unites, COUNT(p.id) AS nbpieces')
            ->andWhere('p.identreprise = :ide')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleResult();
    }

    //    /**
    //     * @return Piece[] Returns an array of Piece objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Piece
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
