<?php

namespace App\Repository;

use App\Entity\Detailpersonnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Detailpersonnel>
 */
class DetailpersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detailpersonnel::class);
    }

    public function affectationsParPersonnel(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array {
        // Voyages
        $voyages = $this->createQueryBuilder('dp')
            ->select('IDENTITY(dp.personnel) AS personnelid, COUNT(dp.id) AS nbvoyages')
            ->andWhere('dp.voyage IS NOT NULL')
            ->andWhere('dp.depannage IS NULL')
            ->join('dp.voyage', 'v')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('dp.personnel')
            ->getQuery()
            ->getArrayResult();

        // Dépannages
        $depannages = $this->createQueryBuilder('dp')
            ->select('IDENTITY(dp.personnel) AS personnelid, COUNT(dp.id) AS nbdepannages')
            ->andWhere('dp.depannage IS NOT NULL')
            ->andWhere('dp.voyage IS NULL')
            ->join('dp.depannage', 'd')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepannage >= :debut')
            ->andWhere('d.datedepannage <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('dp.personnel')
            ->getQuery()
            ->getArrayResult();

        // Index par personnelid
        $index = [];
        foreach ($voyages as $row) {
            $index[(int)$row['personnelid']]['nbvoyages'] = (int)$row['nbvoyages'];
        }
        foreach ($depannages as $row) {
            $index[(int)$row['personnelid']]['nbdepannages'] = (int)$row['nbdepannages'];
        }

        return $index;
    }

    //    /**
    //     * @return Detailpersonnel[] Returns an array of Detailpersonnel objects
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

    //    public function findOneBySomeField($value): ?Detailpersonnel
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
