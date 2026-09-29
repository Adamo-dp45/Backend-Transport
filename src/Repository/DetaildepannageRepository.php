<?php

namespace App\Repository;

use App\Entity\Detaildepannage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Detaildepannage>
 */
class DetaildepannageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detaildepannage::class);
    }

    //    /**
    //     * @return Detaildepannage[] Returns an array of Detaildepannage objects
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

    //    public function findOneBySomeField($value): ?Detaildepannage
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Total des PIÈCES déjà enregistrées sur un dépannage.
     *
     * Symétrique de 'DetailmaindoeuvreRepository::totalPourDepannage()' : sert au recalcul de
     * 'Depannage::$couttotal' quand la modification ne touche PAS aux pièces.
     */
    public function totalPourDepannage(int $depannageId): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.prixunitaire * d.quantite), 0)')
            ->andWhere('d.depannage = :depannage')
            ->setParameter('depannage', $depannageId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
