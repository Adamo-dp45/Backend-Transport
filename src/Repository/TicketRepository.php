<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    // -- Bordereau -- //

    public function findBordereauStats(int $voyageId, int $gareId, int $identreprise): array
    {
        $kpis = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->andWhere('t.voyage = :voyageId')
            ->andWhere('t.gare = :gareId')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->setParameter('voyageId', $voyageId)
            ->setParameter('gareId', $gareId)
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleResult();

        return [
            'nbtickets' => (int)$kpis['nbtickets'],
            'recette' => (float)$kpis['recette']
        ];
    }

    public function findPassagers(int $voyageId, int $gareId, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't.codeticket',
                't.nomclient',
                't.contactclient',
                't.prix',
                's.numero AS siegenumero',
                't.createdAt AS createdat',
            )
            ->join('t.siege', 's')
            ->andWhere('t.voyage = :voyageId')
            ->andWhere('t.gare = :gareId')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->setParameter('voyageId', $voyageId)
            ->setParameter('gareId', $gareId)
            ->setParameter('ide', $identreprise)
            ->orderBy('s.numero', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /* Bordereau chauffeur
     */
    public function findByVoyage(int $voyageId, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't.codeticket',
                't.nomclient',
                't.contactclient',
                't.prix',
                's.numero AS siegenumero',
            )
            ->join('t.siege', 's')
            ->andWhere('t.voyage = :voyageId')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('ide', $identreprise)
            ->orderBy('s.numero', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Statistiques -- //

    public function recettesTotales(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('t')
            ->select('SUM(t.prix) AS total')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.reservation IS NULL') // hors billets de réservation : leur recette est reconnue AU PAIEMENT de la réservation (anti double-comptage)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    /**
     * Recette des ventes COMMERCIALES (vendeur à bord) regroupée par GARE D'AFFECTATION du commercial
     * (sa gare de rattachement, User.gare), et NON la gare de montée : la vente du commercial alimente
     * la recette de SA gare. Les commerciaux sans gare (centraux) sont exclus (jointure interne).
     */
    public function recetteCommercialeParGareAffectation(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.commercial', 'u')
            ->join('u.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette des billets issus d'une RÉSERVATION (payés sur le compte admin, hors caisse gare).
     * 3e canal, complément de recetteParGare (guichet) et recetteParCommercial (à bord) : les trois
     * PARTITIONNENT la recette billets VALIDE (gare XOR commercial XOR réservation).
     */
    public function recettesReservation(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('t')
            ->select('SUM(t.prix) AS total')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.reservation IS NOT NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    /**
     * Recette GUICHET (vente au comptoir de la gare) : ni commercial (à bord) ni réservation. Somme des
     * recetteParGare, exposée en scalaire pour la ventilation par canal de la billetterie.
     */
    public function recettesGuichet(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('t')
            ->select('SUM(t.prix) AS total')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.commercial IS NULL')
            ->andWhere('t.reservation IS NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return round((float)($row['total'] ?? 0), 2);
    }

    // -- Billetterie -- //

    public function countTotal(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function recettesParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('DATE(t.createdAt) AS label, SUM(t.prix) AS montant, COUNT(t.id) AS nbtickets')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.reservation IS NULL') // ventes directes : la recette résa est reconnue au paiement (hors billetterie)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function recettesParTrajet(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        /*
            - Agrégé par LIGNE (et non plus par trajet) : couvre les voyages créés sur une ligne sans trajet.
              Le nom de méthode / le champ 'trajet' du DTO sont conservés (contrat JSON inchangé, dashboard non cassé) ;
              renommage cosmétique 'trajet' -> 'ligne' à faire avec le frontend (étape 3/4).
        */
        return $this->createQueryBuilder('t')
            ->select('COALESCE(l.libelle, l.codeligne) AS trajet, SUM(t.prix) AS montant, COUNT(t.id) AS nbtickets')
            ->join('t.voyage', 'v')
            ->join('v.ligne', 'l')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.reservation IS NULL') // ventes directes (hors réservation)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function recettesParCar(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.matricule, SUM(t.prix) AS montant, COUNT(t.id) AS nbtickets')
            ->join('t.voyage', 'v')
            ->join('v.car', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.reservation IS NULL') // ventes directes (hors réservation)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.matricule')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Agent -- //

    public function performancesParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'u.id',
                'u.nom',
                'u.prenom',
                'COUNT(t.id) AS nbtickets',
                'SUM(t.prix) AS recette',
            )
            ->join(User::class, 'u', 'WITH', 'u.id = t.createdBy')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.commercial IS NULL') // performance GUICHET : la vente à bord relève de la recette commercial
            ->andWhere('t.reservation IS NULL') // et les émissions de réservation (payées sur compte admin) ne sont pas la vente de l'agent
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->andWhere('u.entreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('u.id')
            ->orderBy('recette', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Caisse -- //

    public function detailParAgentEtVoyage(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'u.id AS agentid',
                'u.nom',
                'u.prenom',
                'v.codevoyage',
                'v.provenance',
                'v.destination',
                'COUNT(t.id) AS nbtickets',
                'SUM(t.prix) AS recette',
            )
            ->join(User::class, 'u', 'WITH', 'u.id = t.createdBy')
            ->join('t.voyage', 'v')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.commercial IS NULL') // caisse GUICHET uniquement : les ventes à bord (commercial) ont leur propre recette
            ->andWhere('t.reservation IS NULL') // et hors billets de réservation (payés sur compte admin)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('u.id, v.id')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('recette', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function detailParJourEtVoyage(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'DATE(t.createdAt) AS jour',
                'v.codevoyage',
                'v.provenance',
                'v.destination',
                'COUNT(t.id) AS nbtickets',
                'SUM(t.prix) AS recette',
            )
            ->join('t.voyage', 'v')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'") // exclut les billets désistés (reportés/annulés) des recettes/bordereaux
            ->andWhere('t.commercial IS NULL') // caisse GUICHET (cohérent avec parGare/parAgent)
            ->andWhere('t.reservation IS NULL') // et hors billets de réservation (payés sur compte admin)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('jour, v.id')
            ->orderBy('jour', 'ASC')
            ->addOrderBy('recette', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Par gare (multi-gares) -- //

    /**
     * Désistements par gare d'émission : nombre de billets ANNULÉS et REPORTÉS par gare sur la période
     * (selon la date d'émission du billet, cohérent avec les autres métriques de la page « par gare »).
     *
     * @return array<int, array{gareid:int, garelibelle:string, nbannules:int, nbreportes:int}>
     */
    public function desistementsParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                'g.id AS gareid',
                'g.libelle AS garelibelle',
                "SUM(CASE WHEN t.statut = 'ANNULE' THEN 1 ELSE 0 END) AS nbannules",
                "SUM(CASE WHEN t.statut = 'REPORTE' THEN 1 ELSE 0 END) AS nbreportes"
            )
            ->join('t.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut IN ('ANNULE', 'REPORTE')")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette billetterie groupée par gare de MONTÉE (gare d'émission du billet).
     */
    public function recetteParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.commercial IS NULL') // ventes commerciales exclues : elles alimentent la recette du commercial
            ->andWhere('t.reservation IS NULL') // billets issus d'une réservation exclus : payés sur le compte admin, pas dans la caisse gare
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette billets par gare (montée) ET par jour — pour les séries temporelles / sparklines. */
    public function recetteParGareEtJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, DATE(t.createdAt) AS jour, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.commercial IS NULL') // recette de gare = hors ventes commerciales
            ->andWhere('t.reservation IS NULL') // et hors billets de réservation (payés sur compte admin)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('jour')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette billets par gare (montée) ET par agent (createdBy) — croisement caisse. */
    public function recetteParGareEtAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, t.createdBy AS agentid, COUNT(t.id) AS nb, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.commercial IS NULL') // agents de guichet uniquement (le commercial a sa propre recette)
            ->andWhere('t.reservation IS NULL') // et hors billets de réservation (payés sur compte admin)
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('t.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette des VENTES COMMERCIALES (vendeur à bord) groupée par commercial. Complément de
     * 'recetteParGare' : ensemble ils PARTITIONNENT la recette billets VALIDE (gare XOR commercial).
     */
    public function recetteParCommercial(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.id AS commercialid, c.nom AS nom, c.prenom AS prenom, COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.commercial', 'c') // INNER JOIN → seules les ventes commerciales (t.commercial non nul)
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette + nombre de billets vendus par UN commercial, groupés PAR VOYAGE (pour son espace).
     * @return array<int, array{voyageid:int, nbtickets:int, recette:int}>
     */
    public function recetteCommercialeParVoyage(int $commercialId, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('IDENTITY(t.voyage) AS voyageid, COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.commercial = :com')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.voyage IS NOT NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('com', $commercialId)
            ->groupBy('voyageid')
            ->getQuery()
            ->getArrayResult();
    }

    /** Descentes par gare (billets dont garedescente = la gare). Exclut les descentes nulles (legacy). */
    public function descentesParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(t.id) AS nb')
            ->join('t.garedescente', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Top tronçons (gare montée → gare descente) : volume + recette. */
    public function topTroncons(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, int $limit = 12): array
    {
        return $this->createQueryBuilder('t')
            ->select('gm.id AS deId, gm.libelle AS de, gd.id AS versId, gd.libelle AS vers, COUNT(t.id) AS nb, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.gare', 'gm')
            ->join('t.garedescente', 'gd')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('gm.id')
            ->addGroupBy('gd.id')
            ->orderBy('nb', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /** Billets vendus + recette par gare de DÉPART EFFECTIVE (v.gareprovenance : gare intermédiaire pour un départ partiel, sinon origine de la ligne), voyages partant sur la période. */
    public function billetsParGareDepart(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        // billets = tous les billets (occupation des sièges, tout canal). La recette est SCINDÉE par canal
        // (guichet / commercial / réservation) : seul le guichet est de la caisse gare ; les deux autres
        // vont au commercial et au compte admin. recette = total (les 3 canaux réunis).
        return $this->createQueryBuilder('t')
            ->select(
                'go.id AS gareid',
                'COUNT(t.id) AS billets',
                'COALESCE(SUM(t.prix), 0) AS recette',
                'COALESCE(SUM(CASE WHEN t.commercial IS NULL AND t.reservation IS NULL THEN t.prix ELSE 0 END), 0) AS recette_gare',
                'COALESCE(SUM(CASE WHEN t.commercial IS NOT NULL THEN t.prix ELSE 0 END), 0) AS recette_commercial',
                'COALESCE(SUM(CASE WHEN t.reservation IS NOT NULL THEN t.prix ELSE 0 END), 0) AS recette_reservation',
            )
            ->join('t.voyage', 'v')
            ->join('v.gareprovenance', 'go')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('go.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette des ventes COMMERCIALES (vendeur à bord) ventilée par trajet (gare montée → descente).
     * Complète 'recetteParCommercial' : où le commercial capte-t-il sa recette ? (stats commerciales)
     */
    public function recetteCommercialeParTrajet(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, int $limit = 12): array
    {
        return $this->createQueryBuilder('t')
            ->select('gm.libelle AS de, gd.libelle AS vers, COUNT(t.id) AS nbtickets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.commercial', 'c') // INNER JOIN → ventes commerciales uniquement
            ->join('t.gare', 'gm')
            ->join('t.garedescente', 'gd')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('gm.id')
            ->addGroupBy('gd.id')
            ->orderBy('recette', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette billets par gare de DÉPART EFFECTIVE (v.gareprovenance), avec libellé/ville et l'origine de
     * la ligne pour distinguer, PAR gare, la part captée EN TANT QU'ORIGINE vs EN TANT QU'INTERMÉDIAIRE
     * (départ partiel). Groupé par (gareprovenance, origine de ligne) : une même gare peut être l'origine
     * d'une ligne et une étape intermédiaire d'une autre.
     */
    public function departsParGareProvenance(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('go.id AS gareid, go.libelle AS libelle, gv.nom AS ville, lo.id AS origineid, COUNT(t.id) AS billets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.voyage', 'v')
            ->join('v.gareprovenance', 'go')
            ->leftJoin('go.ville', 'gv') // Gare.ville = relation ManyToOne vers Ville → on prend son nom
            ->join('v.ligne', 'l')
            ->join('l.gareorigine', 'lo')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.reservation IS NULL') // hors billets de réservation : leur recette est ajoutée à part (au paiement) — anti double-comptage
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('go.id')
            ->addGroupBy('lo.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette/billets ventilés selon le TYPE de départ du voyage : 'complet' (la gare de provenance EST
     * l'origine de la ligne) ou 'partiel' (départ depuis une gare intermédiaire). Classé en PHP pour
     * éviter un GROUP BY sur expression CASE. Sert à la part de recette captée en départ partiel.
     */
    public function recetteParTypeDepart(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('go.id AS provid, lo.id AS origid, COUNT(t.id) AS billets, COALESCE(SUM(t.prix), 0) AS recette')
            ->join('t.voyage', 'v')
            ->join('v.gareprovenance', 'go')
            ->join('v.ligne', 'l')
            ->join('l.gareorigine', 'lo')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.reservation IS NULL') // hors billets de réservation : recette ajoutée à part — anti double-comptage
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('go.id')
            ->addGroupBy('lo.id')
            ->getQuery()
            ->getArrayResult();

        $out = ['complet' => ['billets' => 0, 'recette' => 0], 'partiel' => ['billets' => 0, 'recette' => 0]];
        foreach ($rows as $r) {
            $type = ((int) $r['provid'] === (int) $r['origid']) ? 'complet' : 'partiel';
            $out[$type]['billets'] += (int) $r['billets'];
            $out[$type]['recette'] += (int) $r['recette'];
        }
        return $out;
    }

    /** Répartition des billets par statut (VALIDE / REPORTE / ANNULE) — désistements. */
    public function compteParStatut(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.statut AS statut, COUNT(t.id) AS nb')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.statut')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Taux d'annulation des VENTES d'un agent : parmi les billets qu'il a ÉMIS au guichet (createdBy, hors
     * réservation, hors vente à bord), combien sont désormais ANNULE. nbemis (dénominateur) + nbannules.
     * @return array<int, array{agentid:int, nbemis:int, nbannules:int}>
     */
    public function tauxAnnulationParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't.createdBy AS agentid',
                'COUNT(t.id) AS nbemis',
                "SUM(CASE WHEN t.statut = 'ANNULE' THEN 1 ELSE 0 END) AS nbannules"
            )
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.reservation IS NULL')
            ->andWhere('t.commercial IS NULL')
            ->andWhere('t.createdBy IS NOT NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * TOTAL des remises accordées (billets VALIDE, remise > 0) sur la période — TOUS bénéficiaires confondus,
     * Y COMPRIS sans bénéficiaire (celui-ci est facultatif). À utiliser pour le total/taux/moyenne, car
     * remisesParBeneficiaire (jointure interne) exclut les remises sans bénéficiaire.
     * @return array{total:int, nb:int}
     */
    public function remisesTotales(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.remise), 0) AS total', 'COUNT(t.id) AS nb')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.remise > 0')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return ['total' => (int) $row['total'], 'nb' => (int) $row['nb']];
    }

    /** Remises accordées (billets VALIDE, remise > 0) par bénéficiaire (avec sa catégorie). */
    public function remisesParBeneficiaire(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('b.nom AS nom, b.categorie AS categorie, COALESCE(SUM(t.remise), 0) AS total, COUNT(t.id) AS nb')
            ->join('t.beneficiaire', 'b')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.remise > 0')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Annulations de billets par AGENT qui les a annulés (updatedBy), sur la période d'annulation
     * (datedesistement) : nb, montant remboursé (SUM prix), et nb « auto-annulé » (vendu ET annulé par le
     * MÊME agent = createdBy == updatedBy → signal fort d'annulation après encaissement).
     * @return array<int, array{agentid:int, nb:int, montant:int, nbself:int}>
     */
    public function annulationsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't.updatedBy AS agentid',
                'COUNT(t.id) AS nb',
                'COALESCE(SUM(t.prix), 0) AS montant',
                'SUM(CASE WHEN t.createdBy = t.updatedBy THEN 1 ELSE 0 END) AS nbself'
            )
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'ANNULE'")
            ->andWhere('t.datedesistement >= :debut')
            ->andWhere('t.datedesistement <= :fin')
            ->andWhere('t.updatedBy IS NOT NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.updatedBy')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Suppressions de billets par AGENT qui les a supprimés (deletedBy), sur la période de suppression
     * (deletedAt) — détection de « vente hors-livre » (billet créé puis retiré du livre). montant = Σ prix
     * des billets VALIDE supprimés (recette qui disparaît des listes) ; nbapresdepart = supprimés APRÈS le
     * départ réel du voyage (`v.datedepartreelle <= t.deletedAt`) = signal fort (le passager a voyagé).
     * @return array<int, array{agentid:int, nb:int, montant:int, nbapresdepart:int}>
     */
    public function suppressionsParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select(
                't.deletedBy AS agentid',
                'COUNT(t.id) AS nb',
                "COALESCE(SUM(CASE WHEN t.statut = 'VALIDE' THEN t.prix ELSE 0 END), 0) AS montant",
                'SUM(CASE WHEN v.datedepartreelle IS NOT NULL AND v.datedepartreelle <= t.deletedAt THEN 1 ELSE 0 END) AS nbapresdepart'
            )
            ->leftJoin('t.voyage', 'v')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->andWhere('t.deletedAt >= :debut')
            ->andWhere('t.deletedAt <= :fin')
            ->andWhere('t.deletedBy IS NOT NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.deletedBy')
            ->orderBy('montant', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Remises accordées au GUICHET par AGENT (createdBy) — remises à bord exclues (voir remisesParCommercial).
     * @return array<int, array{agentid:int, total:int, nb:int}>
     */
    public function remisesParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.createdBy AS agentid, COALESCE(SUM(t.remise), 0) AS total, COUNT(t.id) AS nb')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.remise > 0')
            ->andWhere('t.commercial IS NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.createdBy')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Remises accordées À BORD par COMMERCIAL. @return array<int, array{commercialid:int, nom:string, prenom:string, total:int, nb:int}>
     */
    public function remisesParCommercial(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.id AS commercialid, c.nom AS nom, c.prenom AS prenom, COALESCE(SUM(t.remise), 0) AS total, COUNT(t.id) AS nb')
            ->join('t.commercial', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.remise > 0')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Remises accordées par GARE (gare de montée = gare de vente au guichet) — repère où se concentrent
     * les remises (signal anti-abus). @return array<int, array{gareid:int, garelibelle:string, total:int, nb:int}>
     */
    public function remisesParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COALESCE(SUM(t.remise), 0) AS total, COUNT(t.id) AS nb')
            ->join('t.gare', 'g')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.remise > 0')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Matrice Origine → Destination (billets VALIDE) : volume par couple (montée, descente). */
    public function matriceOD(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('gm.id AS deId, gm.libelle AS de, gd.id AS versId, gd.libelle AS vers, COUNT(t.id) AS nb')
            ->join('t.gare', 'gm')
            ->join('t.garedescente', 'gd')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('gm.id')
            ->addGroupBy('gd.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Billets VALIDE par heure de création (00–23) — heures de pointe. */
    public function billetsParHeure(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select("DATE_FORMAT(t.createdAt, '%H') AS heure, COUNT(t.id) AS nb")
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('heure')
            ->orderBy('heure', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // -- Clients -- //

    /**
     * Clients actifs sur la période (ayant au moins un billet VALIDE) + recette totale associée.
     * @return array{clients:int, recette:int}
     */
    public function statsClientsActifs(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('COUNT(DISTINCT IDENTITY(t.client)) AS clients', 'COALESCE(SUM(t.prix), 0) AS recette')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.client IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return ['clients' => (int) $row['clients'], 'recette' => (int) $row['recette']];
    }

    /**
     * Top clients sur la période, triés par nombre de billets OU par dépense.
     * @param 'nb'|'depense' $tri
     * @return array<int, array{id:int, nom:string, contact:?string, membre:bool, nb:int, depense:int}>
     */
    public function topClients(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, string $tri = 'nb', int $limit = 10): array
    {
        $orderBy = $tri === 'depense' ? 'depense' : 'nb';

        return $this->createQueryBuilder('t')
            ->select('c.id AS id', 'c.nom AS nom', 'c.contact AS contact', 'c.fidelite AS membre',
                'COUNT(t.id) AS nb', 'COALESCE(SUM(t.prix), 0) AS depense')
            ->join('t.client', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('c.id')
            ->orderBy($orderBy, 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    // -- Fidélité (carte à tampons) -- //

    /**
     * Nombre de voyages « tamponnables » d'un client : billets VALIDE, NON-récompense, émis depuis
     * l'adhésion. Base du cumul de la carte à tampons (cf. App\Domain\Service\FideliteService).
     */
    public function countVoyagesFidelite(Client $client, ?\DateTimeImmutable $depuis): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.client = :client')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = false')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('client', $client);

        if ($depuis !== null) {
            $qb->andWhere('t.createdAt >= :depuis')->setParameter('depuis', $depuis);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Nombre de récompenses fidélité déjà utilisées par un client (billets-récompense VALIDE).
     * Chaque récompense consomme 'seuil' tampons.
     */
    public function countRecompensesFidelite(Client $client): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.client = :client')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = true')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récompenses fidélité émises sur la période : nombre + valeur offerte (somme des remises).
     * @return array{nb:int, valeur:int}
     */
    public function statsRecompensesPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('t')
            ->select('COUNT(t.id) AS nb', 'COALESCE(SUM(t.remise), 0) AS valeur')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = true')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return ['nb' => (int) $row['nb'], 'valeur' => (int) $row['valeur']];
    }

    /**
     * Voyages « tamponnables » cumulés PAR MEMBRE (VALIDE, non-récompense, depuis l'adhésion).
     * @return array<int, array{clientid:int, nb:int}>
     */
    public function voyagesParMembre(int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('IDENTITY(t.client) AS clientid', 'COUNT(t.id) AS nb')
            ->join('t.client', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('c.fidelite = true')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = false')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdAt >= c.dateadhesion')
            ->setParameter('ide', $identreprise)
            ->groupBy('t.client')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Récompenses utilisées PAR MEMBRE (billets-récompense VALIDE).
     * @return array<int, array{clientid:int, nb:int}>
     */
    public function recompensesParMembre(int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('IDENTITY(t.client) AS clientid', 'COUNT(t.id) AS nb')
            ->join('t.client', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('c.fidelite = true')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = true')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->groupBy('t.client')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Récompenses fidélité appliquées PAR AGENT (createdBy du billet-récompense) sur la période :
     * nb + valeur offerte (Σ remise). Anti « fidélité détournée » : qui distribue/encaisse le plus de récompenses.
     * @return array<int, array{agentid:int, nb:int, valeur:int}>
     */
    public function recompensesParAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.createdBy AS agentid', 'COUNT(t.id) AS nb', 'COALESCE(SUM(t.remise), 0) AS valeur')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = true')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdBy IS NOT NULL')
            ->andWhere('t.createdAt >= :debut')
            ->andWhere('t.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('t.createdBy')
            ->orderBy('valeur', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Tampons (billets accumulateurs VALIDE hors récompense, depuis l'adhésion) répartis PAR MEMBRE et par
     * VENDEUR (createdBy) — détection des cartes « captées » par un seul agent. Cumulatif (pas de période).
     * @return array<int, array{clientid:int, nom:?string, contact:?string, agentid:int, nb:int}>
     */
    public function accumulateursParMembreVendeur(int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.id AS clientid', 'c.nom AS nom', 'c.contact AS contact', 't.createdBy AS agentid', 'COUNT(t.id) AS nb')
            ->join('t.client', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('c.fidelite = true')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = false')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdAt >= c.dateadhesion')
            ->andWhere('t.createdBy IS NOT NULL')
            ->setParameter('ide', $identreprise)
            ->groupBy('c.id')
            ->addGroupBy('t.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Récompenses fidélité PAR MEMBRE et par VENDEUR (createdBy du billet-récompense) — complément de
     * accumulateursParMembreVendeur pour repérer « le même agent nourrit ET encaisse la carte ».
     * @return array<int, array{clientid:int, agentid:int, nb:int}>
     */
    public function recompensesParMembreVendeur(int $identreprise): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.id AS clientid', 't.createdBy AS agentid', 'COUNT(t.id) AS nb')
            ->join('t.client', 'c')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('c.fidelite = true')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.fideliteRecompense = true')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.createdBy IS NOT NULL')
            ->setParameter('ide', $identreprise)
            ->groupBy('c.id')
            ->addGroupBy('t.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Ticket[] Returns an array of Ticket objects
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

    //    public function findOneBySomeField($value): ?Ticket
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
