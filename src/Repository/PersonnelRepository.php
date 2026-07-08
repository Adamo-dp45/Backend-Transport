<?php

namespace App\Repository;

use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Personnel>
 */
class PersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personnel::class);
    }

    public function countTotal(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.identreprise = :ide')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllAvecType(int $identreprise): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.nom, p.prenom, tp.libelle AS type')
            ->join('p.typepersonnel', 'tp')
            ->andWhere('p.identreprise = :ide')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Personnel[] Returns an array of Personnel objects
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

    //    public function findOneBySomeField($value): ?Personnel
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
