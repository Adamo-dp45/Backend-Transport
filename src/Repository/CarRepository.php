<?php

namespace App\Repository;

use App\Entity\Car;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Car>
 */
class CarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Car::class);
    }

    public function countTotal(int $identreprise): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countParEtat(int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.etat, COUNT(c.id) AS total')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->groupBy('c.etat')
            ->orderBy('c.etat', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- FlotteActivity -- //

    public function findAllAvecEtat(int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id, c.matricule, c.etat')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->orderBy('c.matricule', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countEnVoyage(int $identreprise): int
    {
        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.etat = :etat')
            ->setParameter('ide', $identreprise)
            ->setParameter('etat', 'Mission')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Car[] Returns an array of Car objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Car
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
