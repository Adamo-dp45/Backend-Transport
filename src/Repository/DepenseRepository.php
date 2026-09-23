<?php

namespace App\Repository;

use App\Entity\Depense;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Depense>
 *
 * Les agrégats de ce repository alimentent le BÉNÉFICE (FinancierStatsProvider), le RÉSULTAT d'une
 * gare (DepenseGareService) et l'écran d'analyse des dépenses. Tous suivent la même règle :
 *
 *  - période sur 'datedepense' (la date du geste), jamais sur 'createdAt' — une dépense de la
 *    semaine dernière saisie aujourd'hui appartient à la semaine dernière ;
 *  - 'deletedAt IS NULL' : une dépense en corbeille ne pèse plus sur le résultat. Contrairement au
 *    numéro de départ, aucun imprimé ne la porte, rien n'oblige à la garder au compte ;
 *  - jamais de filtre par gare ici : c'est l'appelant qui borne (cf. GareDashboardController), sur
 *    le modèle de 'RecetteGareService'.
 */
class DepenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Depense::class);
    }

    /** Total des dépenses de l'entreprise sur la période — le 3e poste soustrait au bénéfice. */
    public function coutTotal(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('d')
            ->select('SUM(d.montant) AS total')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    /** Série temporelle, pour le graphique des coûts (3e courbe à côté des dépannages et des appros). */
    public function coutParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('DATE(d.datedepense) AS label, SUM(d.montant) AS montant')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Dépenses par GARE. Les dépenses du siège (gare nulle) sont EXCLUES par la jointure interne :
     * elles ont leur propre total (cf. totalSiege), parce qu'on ne les impute à personne.
     */
    public function totalParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(d.id) AS nbdepenses, COALESCE(SUM(d.montant), 0) AS montant')
            ->join('d.gare', 'g')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Ce que le SIÈGE a dépensé (loyer, salaires de la direction) : les lignes sans gare. */
    public function totalSiege(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('d')
            ->select('COUNT(d.id) AS nbdepenses, COALESCE(SUM(d.montant), 0) AS montant')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.gare IS NULL')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return ['nbdepenses' => (int)($row['nbdepenses'] ?? 0), 'montant' => (int)($row['montant'] ?? 0)];
    }

    /**
     * Ventilation par poste, bornée à une gare quand $gareId est fourni, au siège quand $siegeSeul
     * est vrai, à toute l'entreprise sinon. C'est l'axe « où part l'argent ».
     */
    public function totalParType(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise,
        ?int $gareId = null,
        bool $siegeSeul = false
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->select('t.id AS typeid, t.libelle AS typelibelle, COUNT(d.id) AS nbdepenses, COALESCE(SUM(d.montant), 0) AS montant')
            ->join('d.typedepense', 't')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.id')
            ->orderBy('montant', 'DESC');

        if ($gareId !== null) {
            $qb->andWhere('d.gare = :gare')->setParameter('gare', $gareId);
        } elseif ($siegeSeul) {
            $qb->andWhere('d.gare IS NULL');
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** Ventilation par mode de règlement — la base du futur rapprochement de caisse (espèces). */
    public function totalParMode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.modereglement AS mode, COUNT(d.id) AS nbdepenses, COALESCE(SUM(d.montant), 0) AS montant')
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('d.modereglement')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Ventilation par mois (AAAA-MM) sur la période — l'évolution des charges. */
    public function totalParMois(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('d')
            ->select("DATE_FORMAT(d.datedepense, '%Y-%m') AS label, COALESCE(SUM(d.montant), 0) AS montant")
            ->andWhere('d.identreprise = :ide')
            ->andWhere('d.datedepense >= :debut')
            ->andWhere('d.datedepense <= :fin')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
