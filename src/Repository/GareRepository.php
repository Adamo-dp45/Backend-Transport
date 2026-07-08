<?php

namespace App\Repository;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Gare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gare>
 */
class GareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gare::class);
    }

    /**
     * Gares ACTIVES d'une ville (navigation client publique).
     *
     * @return Gare[]
     */
    public function findActivesParVille(int $villeId, int $identreprise): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.ville = :ville')
            ->andWhere('g.identreprise = :ide')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('g.statut = :actif')
            ->setParameter('ville', $villeId)
            ->setParameter('ide', $identreprise)
            ->setParameter('actif', ReferenceStatus::ACTIF->value)
            ->orderBy('g.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Gare[] Returns an array of Gare objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Gare
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
