<?php

namespace App\Repository;

use App\Domain\Enum\AlerteSeverite;
use App\Domain\Enum\AlerteStatut;
use App\Domain\Service\AlerteAudienceResolver;
use App\Entity\Alerte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alerte>
 */
class AlerteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private AlerteAudienceResolver $audience)
    {
        parent::__construct($registry, Alerte::class);
    }

    /**
     * Alertes NON RÉSOLUES d'une entreprise, indexées par clé de déduplication. Utilisé par le
     * balayeur (réconciliation) : vue SYSTÈME complète, sans audience (aucun utilisateur en CLI).
     *
     * @return array<string, Alerte>
     */
    public function indexParCle(int $identreprise): array
    {
        /** @var Alerte[] $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.statut IN (:vivants)')
            ->setParameter('ide', $identreprise)
            ->setParameter('vivants', AlerteStatut::nonResolus())
            ->getQuery()
            ->getResult();

        $index = [];
        foreach ($rows as $a) {
            $index[$a->getCle()] = $a;
        }

        return $index;
    }

    /**
     * Résumé pour le badge de la cloche : total + ventilation par sévérité et par famille des
     * alertes ACTIVE visibles par l'utilisateur courant (audience appliquée).
     *
     * @return array{total:int, parSeverite:array<string,int>, parFamille:array<string,int>}
     */
    public function resume(int $identreprise): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.severite AS severite, a.famille AS famille')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.statut = :actif')
            ->setParameter('ide', $identreprise)
            ->setParameter('actif', AlerteStatut::ACTIVE->value);
        $this->audience->appliquer($qb, 'a');

        $rows = $qb->getQuery()->getScalarResult();

        $parSeverite = [];
        $parFamille = [];
        foreach ($rows as $r) {
            $parSeverite[$r['severite']] = ($parSeverite[$r['severite']] ?? 0) + 1;
            $parFamille[$r['famille']] = ($parFamille[$r['famille']] ?? 0) + 1;
        }

        return [
            'total' => count($rows),
            'parSeverite' => $parSeverite,
            'parFamille' => $parFamille,
        ];
    }

    /**
     * Alertes récentes pour le dropdown : ACTIVE d'abord, les plus urgentes puis les plus récentes.
     *
     * @return Alerte[]
     */
    public function recentes(int $identreprise, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect("CASE a.severite WHEN :sevC THEN 3 WHEN :sevA THEN 2 ELSE 1 END AS HIDDEN poids")
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.statut = :actif')
            ->setParameter('ide', $identreprise)
            ->setParameter('actif', AlerteStatut::ACTIVE->value)
            ->setParameter('sevC', AlerteSeverite::CRITIQUE->value)
            ->setParameter('sevA', AlerteSeverite::AVERTISSEMENT->value)
            ->orderBy('poids', 'DESC')
            ->addOrderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);
        $this->audience->appliquer($qb, 'a');

        return $qb->getQuery()->getResult();
    }

    /**
     * Marque LUES toutes les alertes ACTIVE visibles par l'utilisateur courant (audience appliquée).
     *
     * @return int nombre d'alertes basculées
     */
    public function marquerToutLu(int $identreprise, int $luePar): int
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.statut = :actif')
            ->setParameter('ide', $identreprise)
            ->setParameter('actif', AlerteStatut::ACTIVE->value);
        $this->audience->appliquer($qb, 'a');

        /** @var Alerte[] $alertes */
        $alertes = $qb->getQuery()->getResult();
        $now = new \DateTimeImmutable();
        foreach ($alertes as $a) {
            $a->setStatut(AlerteStatut::LUE->value)
                ->setLuePar($luePar)
                ->setLueLe($now);
        }
        if ($alertes) {
            $this->getEntityManager()->flush();
        }

        return count($alertes);
    }
}
