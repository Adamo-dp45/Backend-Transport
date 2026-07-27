<?php

namespace App\Repository;

use App\Domain\Enum\BagageStatus;
use App\Entity\Bagage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bagage>
 */
class BagageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bagage::class);
    }

    /* Statistiques
     * Pilotées par le STATUT métier (et non par 'deletedAt') : la corbeille ne gère que la visibilité
     * dans les listes, pas l'historique comptable. La recette ne compte que les bagages réellement pris en
     * charge (EMBARQUE/LIVRE/PERDU) ; un bagage seulement ENREGISTRE n'a pas encore généré de recette.
     */

    public function recettesTotales(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.montant), 0) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult()
        ;

        return round((float)($row['total'] ?? 0), 2);
    }

    public function recettesParAgent(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.createdBy AS agentid, COALESCE(SUM(b.montant), 0) AS montant, COUNT(b.id) AS nbbagages')            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.commercial IS NULL') // guichet : bagages enregistrés par le commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.createdBy')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    public function recettesParJourDetail(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'DATE(b.createdAt) AS label',
                'COALESCE(SUM(b.montant), 0) AS montant',
                'COUNT(b.id) AS nbbagages',
                'COALESCE(SUM(b.poids), 0) AS poids',
            )            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /* Statistiques Bagage
     */
    public function countParStatut(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('b.statut, COUNT(b.id) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.statut')
            ->getQuery()
            ->getArrayResult()
        ;
        $index = [];
        foreach($rows as $row) {
            $index[$row['statut']] = (int)$row['total'];
        }
        return $index;
    }

    public function poidsTotal(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): int
    {
        $row = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.poids), 0) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult()
        ;
        return (int)($row['total'] ?? 0);
    }

    /**
     * Recette bagage groupée par gare de DÉPART (gare d'émission). Compté à la création.
     * Les bagages sans gare de départ sont exclus (jointure interne) ; seuls les statuts pris en charge comptent.
     */
    public function recetteParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette bagages par gare (dépôt) ET par jour — séries temporelles / sparklines. */
    public function recetteParGareEtJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, DATE(b.createdAt) AS jour, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('jour')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette bagages par gare (dépôt) ET par agent (createdBy) — croisement caisse. */
    public function recetteParGareEtAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, b.createdBy AS agentid, COUNT(b.id) AS nb, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('b.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Bagages à MONTANT FORCÉ par agent (createdBy) sur la période — détection de sous-déclaration.
     * nb = bagages forcés ; manque = Σ(tarif − facturé) quand facturé SOUS le tarif (manque à gagner) ;
     * nbsoustarif = combien sont sous le tarif ; nbhorsgrille = forcés sans tarif de référence (hors grille).
     * @return array<int, array{agentid:int, nb:int, manque:int, nbsoustarif:int, nbhorsgrille:int}>
     */
    public function forcagesParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'b.createdBy AS agentid',
                'COUNT(b.id) AS nb',
                'COALESCE(SUM(CASE WHEN tr.montant IS NOT NULL AND b.montant < tr.montant THEN tr.montant - b.montant ELSE 0 END), 0) AS manque',
                'SUM(CASE WHEN tr.montant IS NOT NULL AND b.montant < tr.montant THEN 1 ELSE 0 END) AS nbsoustarif',
                'SUM(CASE WHEN tr.id IS NULL THEN 1 ELSE 0 END) AS nbhorsgrille'
            )
            ->leftJoin('b.tarifbagage', 'tr')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.montantforce = :force')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('force', true)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.createdBy')
            ->orderBy('manque', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Annulations de bagages PAR AGENT qui les a annulés (updatedBy), sur la période d'annulation (updatedAt) :
     * nb + montant (recette annulée). Un bagage ANNULE est figé → updatedAt ≈ moment de l'annulation.
     * @return array<int, array{agentid:int, nb:int, montant:int}>
     */
    public function annulationsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.updatedBy AS agentid', 'COUNT(b.id) AS nb', 'COALESCE(SUM(b.montant), 0) AS montant')
            ->andWhere('b.identreprise = :ide')
            ->andWhere("b.statut = 'ANNULE'")
            ->andWhere('b.updatedBy IS NOT NULL')
            ->andWhere('b.updatedAt >= :debut')
            ->andWhere('b.updatedAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.updatedBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Suppressions de bagages PAR AGENT qui les a supprimés (deletedBy), sur la période (deletedAt) :
     * nb + montant (recette retirée du livre).
     * @return array<int, array{agentid:int, nb:int, montant:int}>
     */
    public function suppressionsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.deletedBy AS agentid', 'COUNT(b.id) AS nb', 'COALESCE(SUM(b.montant), 0) AS montant')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.deletedAt IS NOT NULL')
            ->andWhere('b.deletedBy IS NOT NULL')
            ->andWhere('b.deletedAt >= :debut')
            ->andWhere('b.deletedAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.deletedBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette des bagages enregistrés À BORD (par le commercial), groupée par commercial. Complément de
     * recetteParGare (guichet) : ensemble ils partitionnent la recette bagage active (gare XOR commercial).
     */
    public function recetteParCommercial(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('c.id AS commercialid, c.nom AS nom, c.prenom AS prenom, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.commercial', 'c')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette + nombre de bagages enregistrés par UN commercial, groupés PAR VOYAGE (pour son espace).
     * @return array<int, array{voyageid:int, nbbagages:int, recette:int}>
     */
    public function recetteCommercialeParVoyage(int $commercialId, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('IDENTITY(b.voyage) AS voyageid, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.commercial = :com')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.voyage IS NOT NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('com', $commercialId)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->groupBy('voyageid')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette des bagages enregistrés À BORD (commercial) regroupée par GARE D'AFFECTATION du commercial
     * (User.gare), et non la gare de dépôt : le bagage du commercial alimente la recette de SA gare.
     * Commerciaux sans gare exclus (jointure interne).
     */
    public function recetteCommercialeParGareAffectation(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.commercial', 'u')
            ->join('u.gare', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Bagages émis par gare (garedepart) : nombre + poids total expédié. */
    public function emisParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nb, COALESCE(SUM(b.poids), 0) AS poids')
            ->join('b.garedepart', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Bagages livrés par gare (garedescente, statut LIVRE). */
    public function livresParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nb')
            ->join('b.garedescente', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere("b.statut = 'LIVRE'")
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Incidents bagages par gare de DÉPÔT (garedepart) : annulés + perdus, sur la période (createdAt).
     * @return array<int, array{gareid:int, garelibelle:string, nbannules:int, nbperdus:int}>
     */
    public function incidentsParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'g.id AS gareid',
                'g.libelle AS garelibelle',
                "SUM(CASE WHEN b.statut = 'ANNULE' THEN 1 ELSE 0 END) AS nbannules",
                "SUM(CASE WHEN b.statut = 'PERDU' THEN 1 ELSE 0 END) AS nbperdus"
            )
            ->join('b.garedepart', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere("b.statut IN ('ANNULE', 'PERDU')")
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette + nombre de BAGAGES par LIGNE (via le voyage), tous canaux, sur la période (createdAt).
     * Complète la recette « par ligne » (billets + réservations + courriers + bagages).
     * @return array<int, array{ligneid:int, nbbagages:int, recette:int}>
     */
    public function recetteParLigne(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('l.id AS ligneid, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.voyage', 'v')
            ->join('v.ligne', 'l')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Dépense BAGAGES par CLIENT (via le billet lié → t.client) sur la période — pour intégrer les bagages
     * dans la « dépense » du client (stats clients). @return array<int, array{clientid:int, montant:int, nb:int}>
     */
    public function depenseParClient(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('c.id AS clientid, COALESCE(SUM(b.montant), 0) AS montant, COUNT(b.id) AS nb')
            ->join('b.ticket', 't')
            ->join('t.client', 'c')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    /* Bordereau chauffeur
     */
    public function findByVoyage(int $voyageId, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'b.codebagage',
                't.nomclient AS nomclient', // identité reprise du billet rattaché (colonne bagage supprimée)
                'b.nature',
                'b.type',
                'b.poids',
                'b.montant',
            )
            ->leftJoin('b.ticket', 't')
            ->andWhere('b.voyage = :voyageId')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut != :perdu')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('ide', $identreprise)
            ->setParameter('perdu', 'PERDU')
            ->orderBy('b.codebagage', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Nombre de bagages (hors annulés) déposés à une gare donnée pour un voyage — bordereau de gare.
     * garedepart = gare de dépôt : ce que cette gare charge dans le car.
     */
    public function countByVoyageEtGare(int $voyageId, int $gareId, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.voyage = :voyageId')
            ->andWhere('b.garedepart = :gareId')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('gareId', $gareId)
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    // -- Alertes -- //

    /**
     * Bagages EMBARQUÉS dont le VOYAGE est arrivé depuis plus longtemps que $limite mais qui ne sont
     * toujours pas LIVRÉS : incident de remise. Gare de descente hydratée (idgare).
     *
     * @return Bagage[]
     */
    public function findEmbarquesNonLivresAnterieursA(int $identreprise, \DateTimeImmutable $limite): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.voyage', 'v')
            ->leftJoin('b.garedescente', 'gd')->addSelect('gd')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('b.statut = :embarque')
            ->andWhere('v.datearriveereelle IS NOT NULL')
            ->andWhere('v.datearriveereelle <= :limite')
            ->setParameter('ide', $identreprise)
            ->setParameter('embarque', BagageStatus::STATUT_EMBARQUE->value)
            ->setParameter('limite', $limite)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Bagage[] Returns an array of Bagage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Bagage
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
