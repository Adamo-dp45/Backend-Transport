<?php

namespace App\Repository;

use App\Entity\Tarif;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarif>
 */
class TarifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarif::class);
    }

    /**
     * Prix d'un segment (garedepart → garearrivee) dans la grille GLOBALE de l'entreprise.
     * Indépendant de la ligne : le même couple de gares a un seul prix.
     */
    public function findMontant(int $gareDepartId, int $gareArriveeId, int $entrepriseId): ?Tarif
    {
        return $this->findOneBy([
            'garedepart' => $gareDepartId,
            'garearrivee' => $gareArriveeId,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
    }

    /**
     * Tous les tarifs au départ d'une gare (= destinations desservies + prix), pour la navigation client.
     *
     * @return Tarif[]
     */
    public function findDepuisGare(int $gareDepartId, int $entrepriseId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.garedepart = :dep')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('dep', $gareDepartId)
            ->setParameter('ide', $entrepriseId)
            ->getQuery()
            ->getResult();
    }
}
