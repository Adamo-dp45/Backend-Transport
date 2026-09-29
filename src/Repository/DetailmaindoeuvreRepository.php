<?php

namespace App\Repository;

use App\Entity\Detailmaindoeuvre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Detailmaindoeuvre>
 */
class DetailmaindoeuvreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Detailmaindoeuvre::class);
    }

    /**
     * Total de la main d'œuvre déjà enregistrée sur un dépannage.
     *
     * Sert au recalcul de `Depannage::$couttotal` quand la modification NE TOUCHE PAS à la main
     * d'œuvre : le payload ne la porte pas, ses lignes sont inchangées, et la base en est donc la
     * source. Sans cela, corriger une pièce remettrait le coût total à « pièces seules » et
     * effacerait silencieusement la main d'œuvre du chiffre.
     */
    public function totalPourDepannage(int $depannageId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.montant), 0)')
            ->andWhere('m.depannage = :depannage')
            ->setParameter('depannage', $depannageId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Detailmaindoeuvre> */
    public function findPourDepannage(int $depannageId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.depannage = :depannage')
            ->setParameter('depannage', $depannageId)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
