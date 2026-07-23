<?php

namespace App\Repository;

use App\Entity\Passage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Passage>
 */
class PassageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Passage::class);
    }

    /**
     * Passages horodatés d'un voyage, indexés par id de gare (un seul par gare — contrainte unique).
     *
     * @return array<int, Passage> gareId => Passage
     */
    public function findParVoyageIndexeParGare(int $voyageId): array
    {
        $out = [];
        foreach ($this->findBy(['voyage' => $voyageId]) as $passage) {
            $gareId = $passage->getGare()?->getId();
            if ($gareId !== null) {
                $out[$gareId] = $passage;
            }
        }

        return $out;
    }

    /** Le passage d'un voyage à une gare, ou null s'il n'a pas encore été horodaté. */
    public function findOneParVoyageGare(int $voyageId, int $gareId): ?Passage
    {
        return $this->findOneBy(['voyage' => $voyageId, 'gare' => $gareId]);
    }

    /**
     * Passages ARRIVÉS sur la période (arrivée réelle renseignée), pour les statistiques de ponctualité.
     * Voyage + ligne + arrêts + gares HYDRATÉS : l'appelant calcule le retard via heurePassage (qui lit
     * les tronçons de la ligne) — sans ce fetch-join, un N+1 sur chaque passage.
     *
     * @return Passage[]
     */
    public function findAvecArriveePourPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('v', 'g', 'l', 'a', 'ag')
            ->join('p.voyage', 'v')
            ->join('p.gare', 'g')
            ->leftJoin('v.ligne', 'l')
            ->leftJoin('l.arrets', 'a')
            ->leftJoin('a.gare', 'ag')
            ->andWhere('p.identreprise = :ent')
            ->andWhere('p.arriveeReelle IS NOT NULL')
            ->andWhere('p.arriveeReelle >= :debut')
            ->andWhere('p.arriveeReelle <= :fin')
            ->setParameter('ent', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }
}

