<?php

namespace App\Repository;

use App\Entity\Tarifbagage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarifbagage>
 */
class TarifbagageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarifbagage::class);
    }

    public function findTarifForPoids(float $poids, int $identreprise): ?Tarifbagage
    {
        return $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.poidsmin <= :poids')
            ->andWhere('t.poidsmax IS NULL OR t.poidsmax >= :poids')
            ->setParameter('identreprise', $identreprise)
            ->setParameter('poids', $poids)
            ->orderBy('t.poidsmin', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTrancheIllimitee(int $identreprise, ?int $excludeId = null): ?Tarifbagage
    {
        $query = $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.poidsmax IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('identreprise', $identreprise)
        ;
        if($excludeId !== null) {
            $query->andWhere('t.id != :id')->setParameter('id', $excludeId);
        }
        return $query->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function findChevauchement(
        float $poidsMin,
        ?float $poidsMax,
        int $identreprise,
        ?int $excludeId = null
    ): ?Tarifbagage
    {
        $query = $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('identreprise', $identreprise)
        ;
        if($excludeId !== null) {
            $query->andWhere('t.id != :id')->setParameter('id', $excludeId);
        }

        if($poidsMax !== null) {
            $query
                ->andWhere('t.poidsmin < :poidsMax')
                ->andWhere('t.poidsmax IS NULL OR t.poidsmax > :poidsMin')
                ->setParameter('poidsMax', $poidsMax)
                ->setParameter('poidsMin', $poidsMin)
            ;
        } else {
            $query
                ->andWhere('t.poidsmax IS NULL OR t.poidsmax > :poidsMin')
                ->setParameter('poidsMin', $poidsMin)
            ;
        }

        return $query->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return Tarifbagage[] Returns an array of Tarifbagage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tarifbagage
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
