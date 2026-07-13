<?php

namespace App\Repository;

use App\Entity\Courrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Courrier>
 */
class CourrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Courrier::class);
    }

    /* Statistiques
     * Pilotées par le STATUT métier (et non par 'deletedAt') : la corbeille ne gère que la visibilité
     * dans les listes, pas l'historique comptable. Un courrier ANNULE ne compte jamais dans la recette
     * (les volets « réception » exigent déjà LIVRE, qui exclut de fait ANNULE).
     */
    public function recettesTotales(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.montant), 0) AS total')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            // Paiement à l'envoi dans ce déploiement : recette comptabilisée à la création.
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            /* -- 'modepaiement' (paiement à l'envoi OU à la réception) — désactivé :
            ->andWhere('(
                (c.modepaiement = :envoi AND c.createdAt >= :debut AND c.createdAt <= :fin)
                OR
                (c.modepaiement = :reception AND c.statut = :livre AND c.datepaiement >= :debut AND c.datepaiement <= :fin)
            )')
            ->setParameter('envoi', 'ENVOI')
            ->setParameter('reception', 'RECEPTION')
            ->setParameter('livre', 'LIVRE')
            */
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    public function recettesParAgent(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        // Paiement à l'envoi : recette par agent comptabilisée à la création (createdBy).
        $envois = $this->createQueryBuilder('c')
            ->select('c.createdBy AS agentid, COALESCE(SUM(c.montant), 0) AS montant, COUNT(c.id) AS nbcourriers')
            ->andWhere('c.identreprise = :ide')
            // ->andWhere('c.modepaiement = :envoi') -- modepaiement désactivé (paiement à l'envoi)
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->andWhere("c.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            // ->setParameter('envoi', 'ENVOI')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.createdBy')
            ->getQuery()
            ->getArrayResult();

        /* -- Volet 'réception' (paiement à la livraison) — désactivé (paiement à l'envoi) :
        $receptions = $this->createQueryBuilder('c')
            ->select('c.updatedBy AS agentid, COALESCE(SUM(c.montant), 0) AS montant, COUNT(c.id) AS nbcourriers')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.modepaiement = :reception')
            ->andWhere('c.statut = :livre')
            ->andWhere('c.datepaiement >= :debut')
            ->andWhere('c.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('reception', 'RECEPTION')
            ->setParameter('livre', 'LIVRE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.updatedBy')
            ->getQuery()
            ->getArrayResult();
        */

        $index = [];
        foreach ($envois as $row) {
            $id = $row['agentid'];
            $index[$id]['agentid']     = $id;
            $index[$id]['nbcourriers'] = (int)$row['nbcourriers'];
            $index[$id]['montant']     = (float)$row['montant'];
        }
        /* -- Fusion du volet réception — désactivée :
        foreach ($receptions as $row) {
            $id = $row['agentid'];
            $index[$id]['agentid']     = $id;
            $index[$id]['nbcourriers'] = ($index[$id]['nbcourriers'] ?? 0) + (int)$row['nbcourriers'];
            $index[$id]['montant']     = ($index[$id]['montant'] ?? 0) + (float)$row['montant'];
        }
        */

        return array_values($index);
    }

    public function recettesParJourDetail(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        // Paiement à l'envoi : recette par jour comptabilisée à la création.
        $envois = $this->createQueryBuilder('c')
            ->select('DATE(c.createdAt) AS label, COALESCE(SUM(c.montant), 0) AS montant, COUNT(c.id) AS nbcourriers')
            ->andWhere('c.identreprise = :ide')
            // ->andWhere('c.modepaiement = :envoi') -- modepaiement désactivé (paiement à l'envoi)
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->andWhere("c.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            // ->setParameter('envoi', 'ENVOI')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->getQuery()
            ->getArrayResult()
        ; /*
            - La recette comptabilisé à la création
        */
        /* -- Volet 'réception' (paiement à la livraison) — désactivé (paiement à l'envoi) :
        $receptions = $this->createQueryBuilder('c')
            ->select('DATE(c.datepaiement) AS label, COALESCE(SUM(c.montant), 0) AS montant, COUNT(c.id) AS nbcourriers')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.modepaiement = :reception')
            ->andWhere('c.statut = :livre')
            ->andWhere('c.datepaiement >= :debut')
            ->andWhere('c.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('reception', 'RECEPTION')
            ->setParameter('livre', 'LIVRE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->getQuery()
            ->getArrayResult()
        ;
        */
        $index = [];
        foreach($envois as $row) {
            $index[$row['label']]['montant'] = (float)$row['montant'];
            $index[$row['label']]['nbcourriers'] = (int)$row['nbcourriers'];
        }
        /* -- Fusion du volet réception — désactivée :
        foreach($receptions as $row) {
            $index[$row['label']]['montant'] = ($index[$row['label']]['montant'] ?? 0) + (float)$row['montant'];
            $index[$row['label']]['nbcourriers'] = ($index[$row['label']]['nbcourriers'] ?? 0) + (int)$row['nbcourriers'];
        }
        */
        ksort($index); /*
            - La fusion par jour
        */
        return array_map(
            fn($label, $vals) => ['label' => $label, 'montant' => $vals['montant'], 'nbcourriers' => $vals['nbcourriers']],
            array_keys($index),
            array_values($index)
        );
    }

    /* Statistiques Courrier
     */
    public function countParStatut(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) AS total')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.statut')
            ->getQuery()
            ->getArrayResult()
        ;
        $index = [];
        foreach($rows as $row) {
            $index[$row['statut']] = (int)$row['total'];
        }

        return $index;
    }

    public function recettesParTrajetDetail(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array {
        return $this->createQueryBuilder('c')
            ->select(
                // gd.ville / ga.ville sont des RELATIONS (entité Ville) → interdit en select DQL scalaire.
                // On utilise le libellé de la gare (scalaire), cohérent avec les autres stats par gare.
                'CONCAT(gd.libelle, \' → \', ga.libelle) AS trajet',
                'COALESCE(SUM(c.montant), 0) AS montant',
                'COUNT(c.id) AS nbcourriers',
            )
            ->join('c.garedepart', 'gd')
            ->join('c.garearrivee', 'ga')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->andWhere("c.statut != 'ANNULE'")
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('gd.id, ga.id')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Recette courrier groupée par gare de DÉPART (gare d'émission). Paiement à l'envoi : compté à la création.
     * Les courriers sans gare de départ (EN_ATTENTE, non affectés) sont exclus (jointure interne).
     */
    public function recetteParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(c.id) AS nbcourriers, COALESCE(SUM(c.montant), 0) AS recette')
            ->join('c.garedepart', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette courriers par gare (dépôt) ET par jour — séries temporelles / sparklines. */
    public function recetteParGareEtJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.id AS gareid, DATE(c.createdAt) AS jour, COALESCE(SUM(c.montant), 0) AS recette')
            ->join('c.garedepart', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('jour')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette courriers par gare (dépôt) ET par agent (createdBy) — croisement caisse. */
    public function recetteParGareEtAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.id AS gareid, c.createdBy AS agentid, COUNT(c.id) AS nb, COALESCE(SUM(c.montant), 0) AS recette')
            ->join('c.garedepart', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('c.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /** Courriers reçus par gare (à destination = garearrivee), hors annulés. */
    public function recusParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(c.id) AS nb')
            ->join('c.garearrivee', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Annulations de courriers PAR AGENT qui les a annulés (updatedBy), sur la période (updatedAt) :
     * nb + montant. Un courrier ANNULE est figé → updatedAt ≈ moment de l'annulation.
     * @return array<int, array{agentid:int, nb:int, montant:int}>
     */
    public function annulationsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.updatedBy AS agentid', 'COUNT(c.id) AS nb', 'COALESCE(SUM(c.montant), 0) AS montant')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut = 'ANNULE'")
            ->andWhere('c.updatedBy IS NOT NULL')
            ->andWhere('c.updatedAt >= :debut')
            ->andWhere('c.updatedAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.updatedBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Suppressions de courriers PAR AGENT qui les a supprimés (deletedBy), sur la période (deletedAt) :
     * nb + montant (recette retirée du livre).
     * @return array<int, array{agentid:int, nb:int, montant:int}>
     */
    public function suppressionsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.deletedBy AS agentid', 'COUNT(c.id) AS nb', 'COALESCE(SUM(c.montant), 0) AS montant')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NOT NULL')
            ->andWhere('c.deletedBy IS NOT NULL')
            ->andWhere('c.deletedAt >= :debut')
            ->andWhere('c.deletedAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.deletedBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Incidents courriers par gare de DÉPÔT (garedepart) : annulés + perdus, sur la période (createdAt).
     * @return array<int, array{gareid:int, garelibelle:string, nbannules:int, nbperdus:int}>
     */
    public function incidentsParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'g.id AS gareid',
                'g.libelle AS garelibelle',
                "SUM(CASE WHEN c.statut = 'ANNULE' THEN 1 ELSE 0 END) AS nbannules",
                "SUM(CASE WHEN c.statut = 'PERDU' THEN 1 ELSE 0 END) AS nbperdus"
            )
            ->join('c.garedepart', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut IN ('ANNULE', 'PERDU')")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Courriers en attente de récupération par gare (statut RECEPTIONNE, à destination). */
    public function enAttenteParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(c.id) AS nb')
            ->join('c.garearrivee', 'g')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut = 'RECEPTIONNE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Courriers LIVRE : createdAt + datelivraison (date réelle de remise) pour le délai moyen exact. */
    public function livresPourDelai(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.createdAt AS createdAt, c.datelivraison AS datelivraison')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut = 'LIVRE'")
            ->andWhere('c.datelivraison IS NOT NULL')
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette + nombre de COURRIERS par LIGNE (via le voyage), sur la période (createdAt). Complète la
     * recette « par ligne ». Les courriers sans voyage (donc sans ligne) sont exclus par la jointure.
     * @return array<int, array{ligneid:int, nbcourriers:int, recette:int}>
     */
    public function recetteParLigne(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('l.id AS ligneid, COUNT(c.id) AS nbcourriers, COALESCE(SUM(c.montant), 0) AS recette')
            ->join('c.voyage', 'v')
            ->join('v.ligne', 'l')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id')
            ->getQuery()
            ->getArrayResult();
    }

    /* Bordereau chauffeur
     */
    public function findByVoyage(int $voyageId, int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select(
                'c.codecourrier',
                'c.nomexpediteur',
                'c.nomdestinataire',
                'c.montant',
                'c.modepaiement',
                'gd.libelle AS garedepart',
                'ga.libelle AS garearrivee',
                'COUNT(dc.id) AS nbcolis',
            )
            ->join('c.garedepart', 'gd')
            ->join('c.garearrivee', 'ga')
            ->leftJoin('c.detailcourriers', 'dc')
            ->andWhere('c.voyage = :voyageId')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.statut != :annule')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('ide', $identreprise)
            ->setParameter('annule', 'ANNULE')
            ->groupBy('c.id')
            ->orderBy('c.codecourrier', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Nombre de courriers (hors annulés) déposés à une gare donnée pour un voyage — bordereau de gare.
     * garedepart = gare de dépôt : ce que cette gare charge dans le car.
     */
    public function countByVoyageEtGare(int $voyageId, int $gareId, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.voyage = :voyageId')
            ->andWhere('c.garedepart = :gareId')
            ->andWhere('c.identreprise = :ide')
            ->andWhere("c.statut != 'ANNULE'")
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('gareId', $gareId)
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Courrier[] Returns an array of Courrier objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Courrier
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
