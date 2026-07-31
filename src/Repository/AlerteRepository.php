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
     * Statistiques des alertes d'une entreprise sur une période (vue ADMIN, sans audience) : volumes
     * par famille / sévérité / statut / gare, évolution par jour, et délai moyen de résolution. Agrégation
     * en PHP à partir d'une seule requête (robuste, indépendant des fonctions SQL de date).
     *
     * @return array<string, mixed>
     */
    public function statistiques(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.famille AS famille', 'a.severite AS severite', 'a.statut AS statut', 'a.idgare AS idgare', 'a.createdAt AS createdAt', 'a.resolueLe AS resolueLe')
            ->andWhere('a.identreprise = :ide')
            ->andWhere('a.deletedAt IS NULL')
            ->andWhere('a.createdAt >= :debut')
            ->andWhere('a.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();

        $total = count($rows);
        $parFamille = $parSeverite = $parStatut = $parGare = $parJour = [];
        $resolues = 0;
        $sommeMin = 0;
        $nbMesure = 0;

        foreach ($rows as $r) {
            $parFamille[$r['famille']] = ($parFamille[$r['famille']] ?? 0) + 1;
            $parSeverite[$r['severite']] = ($parSeverite[$r['severite']] ?? 0) + 1;
            $parStatut[$r['statut']] = ($parStatut[$r['statut']] ?? 0) + 1;
            $gid = (int) ($r['idgare'] ?? 0); // 0 = alerte entreprise/direction (sans gare)
            $parGare[$gid] = ($parGare[$gid] ?? 0) + 1;

            $cree = $r['createdAt'];
            $jour = $cree instanceof \DateTimeInterface ? $cree->format('Y-m-d') : substr((string) $cree, 0, 10);
            $parJour[$jour] = ($parJour[$jour] ?? 0) + 1;

            if ($r['resolueLe'] !== null) {
                $resolues++;
                if ($cree instanceof \DateTimeInterface && $r['resolueLe'] instanceof \DateTimeInterface) {
                    $sommeMin += max(0, ($r['resolueLe']->getTimestamp() - $cree->getTimestamp()) / 60);
                    $nbMesure++;
                }
            }
        }
        ksort($parJour);

        return [
            'total' => $total,
            'resolues' => $resolues,
            'actives' => $parStatut['ACTIVE'] ?? 0,
            'lues' => $parStatut['LUE'] ?? 0,
            'tauxResolution' => $total > 0 ? (int) round($resolues / $total * 100) : 0,
            'delaiResolutionMoyenMin' => $nbMesure > 0 ? (int) round($sommeMin / $nbMesure) : null,
            'parFamille' => $parFamille,   // libellé => nb
            'parSeverite' => $parSeverite, // libellé => nb
            'parStatut' => $parStatut,     // libellé => nb
            'parGare' => $parGare,         // idgare (0 = sans gare) => nb
            'parJour' => $parJour,         // 'Y-m-d' => nb
        ];
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
