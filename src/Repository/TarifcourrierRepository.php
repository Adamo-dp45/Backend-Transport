<?php

namespace App\Repository;

use App\Entity\Tarifcourrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarifcourrier>
 */
class TarifcourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarifcourrier::class);
    }

    public function findTarifForValeur(float $valeur, int $identreprise): ?Tarifcourrier
    {
        return $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.valeurmin <= :valeur')
            ->andWhere('t.valeurmax IS NULL OR t.valeurmax >= :valeur')
            ->setParameter('identreprise', $identreprise)
            ->setParameter('valeur', $valeur)
            ->orderBy('t.valeurmin', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Permet de vérifier qu'il n'y a pas déjà une tranche illimitée
     */
    public function findTrancheIllimitee(int $identreprise, ?int $excludeId = null): ?Tarifcourrier
    {
        $query = $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.valeurmax IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('identreprise', $identreprise)
        ;
        if($excludeId !== null) {
            $query->andWhere('t.id != :id')->setParameter('id', $excludeId);
        }
        return $query->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /**
     * Permet de vérifier le chevauchement de tranches
     */
    public function findChevauchement(float $valeurMin, ?float $valeurMax, int $identreprise, ?int $excludeId = null): ?Tarifcourrier
    {
        $query = $this->createQueryBuilder('t')
            ->where('t.identreprise = :identreprise')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('identreprise', $identreprise)
        ;
        if($excludeId !== null) {
            $query->andWhere('t.id != :id')->setParameter('id', $excludeId);
        }
        /*
            - la tranche existante commence avant notre max ou notre max est null et se termine après notre min ou elle est illimitée
        */
        if($valeurMax !== null) {
            $query
                ->andWhere('t.valeurmin < :valeurMax')
                ->andWhere('t.valeurmax IS NULL OR t.valeurmax > :valeurMin')
                ->setParameter('valeurMax', $valeurMax)
                ->setParameter('valeurMin', $valeurMin)
            ;
        } else { /*
            - Si notre tranche est illimitée, chevauche tout ce qui commence après notre min
        */
            $query
                ->andWhere('t.valeurmax IS NULL OR t.valeurmax > :valeurMin')
                ->setParameter('valeurMin', $valeurMin)
            ;
        }

        return $query->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    //    /**
    //     * @return Tarifcourrier[] Returns an array of Tarifcourrier objects
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

    //    public function findOneBySomeField($value): ?Tarifcourrier
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
