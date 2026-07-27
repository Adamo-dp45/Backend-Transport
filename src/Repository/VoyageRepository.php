<?php

namespace App\Repository;

use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Voyage>
 */
class VoyageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Voyage::class);
    }

    /* Statistiques
     */

    /**
     * Nombre total de voyages sur une période
     */
    public function countByPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Voyages RÉELLEMENT PARTIS sur la période, avec leurs arrêts et leurs passages — base du calcul
     * de QUALITÉ de la donnée d'exploitation (combien d'arrêts sont effectivement horodatés).
     *
     * On part des voyages, et non des passages : un voyage dont AUCUN passage n'a été marqué est
     * précisément le pire cas de qualité, et il serait invisible en interrogeant les passages.
     * Filtré sur 'datedepartreelle' : un voyage jamais parti n'a rien à horodater, l'inclure
     * ferait chuter le taux sans qu'aucun agent soit en faute.
     *
     * @return Voyage[]
     */
    public function findPartisAvecPassagesPourPeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('l', 'a', 'ag', 'p', 'pg', 'c')
            ->leftJoin('v.ligne', 'l')
            ->leftJoin('l.arrets', 'a')
            ->leftJoin('a.gare', 'ag')
            ->leftJoin('v.passages', 'p')
            ->leftJoin('p.gare', 'pg')
            ->leftJoin('v.car', 'c')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.datedepartreelle IS NOT NULL')
            ->andWhere('v.datedepartreelle >= :debut')
            ->andWhere('v.datedepartreelle <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }

    /**
     * TOUS les voyages RÉELLEMENT PARTIS d'une ligne (tout l'historique de l'entreprise), avec leurs
     * passages — base du RECALAGE des durées de tronçon (médiane des durées réellement observées).
     *
     * On prend tout l'historique et non une période : plus il y a d'observations, plus la médiane est
     * fiable, et un référentiel ne se recale pas sur trois voyages.
     *
     * @return Voyage[]
     */
    public function findPartisAvecPassagesPourLigne(int $ligneId, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('p', 'pg')
            ->leftJoin('v.passages', 'p')
            ->leftJoin('p.gare', 'pg')
            ->andWhere('v.ligne = :ligne')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.datedepartreelle IS NOT NULL')
            ->setParameter('ligne', $ligneId)
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getResult();
    }

    /** Capacité et nb de voyages par gare de DÉPART EFFECTIVE (v.gareprovenance : gare intermédiaire pour un départ partiel, sinon origine de la ligne), voyages partant sur la période. */
    public function capaciteParGareDepart(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->select('go.id AS gareid, go.libelle AS garelibelle, COUNT(v.id) AS nbvoyages, COALESCE(SUM(v.placestotal), 0) AS capacite')
            ->join('v.gareprovenance', 'go')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
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
     * Compte les voyages de la période selon leur type de départ : 'complets' (gare de provenance = origine
     * de la ligne) vs 'partiels' (départ depuis une gare intermédiaire, gareprovenance ≠ ligne.gareorigine).
     */
    public function countParTypeDepart(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $compte = function (string $operateur) use ($debut, $fin, $identreprise): int {
            return (int) $this->createQueryBuilder('v')
                ->select('COUNT(v.id)')
                ->join('v.gareprovenance', 'go')
                ->join('v.ligne', 'l')
                ->join('l.gareorigine', 'lo')
                ->andWhere('v.identreprise = :ide')
                ->andWhere('v.deletedAt IS NULL')
                ->andWhere('v.datedepartprevue >= :debut')
                ->andWhere('v.datedepartprevue <= :fin')
                ->andWhere("go.id $operateur lo.id")
                ->setParameter('ide', $identreprise)
                ->setParameter('debut', $debut)
                ->setParameter('fin', $fin)
                ->getQuery()
                ->getSingleScalarResult();
        };

        return ['complets' => $compte('='), 'partiels' => $compte('<>')];
    }

    public function countParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->select("DATE(v.datedepartprevue) AS label, COUNT(v.id) AS total")
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // La moyenne globale du taux de remplissage est calculée en PHP dans 'ExploitationStatsProvider'
    // (AVG des taux par voyage de 'tauxRemplissageParVoyage'), pas par une requête dédiée.

    /**
     * Détail taux de remplissage par voyage sur la période.
     * 'placesoccupees' = nombre de tickets ACTIFS (VALIDE, deletedAt IS NULL) compté à la volée
     * (l'ancienne colonne stockée a été supprimée ; les billets désistés ne comptent pas).
     * Le 'taux' est calculé côté provider.
     */
    public function tauxRemplissageParVoyage(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->select(
                'v.id AS voyageId',
                'v.codevoyage',
                'v.provenance',
                'v.destination',
                'v.datedepartprevue',
                'v.placestotal',
                "(SELECT COUNT(t.id) FROM App\Entity\Ticket t WHERE t.voyage = v AND t.deletedAt IS NULL AND t.statut = 'VALIDE') AS placesoccupees"
            )
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('v.datedepartprevue', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countByStatut(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        // Statut basé sur l'EXÉCUTION RÉELLE : terminé = arrivée réelle ; en cours = parti (départ réel)
        // mais pas arrivé ; planifié = pas encore parti. (Avant : départ PRÉVU passé comme proxy de « parti ».)
        return $this->createQueryBuilder('v')
            ->select(
                'SUM(CASE WHEN v.datearriveereelle IS NOT NULL THEN 1 ELSE 0 END) AS termine',
                'SUM(CASE WHEN v.datearriveereelle IS NULL AND v.datedepartreelle IS NOT NULL THEN 1 ELSE 0 END) AS en_cours',
                'SUM(CASE WHEN v.datearriveereelle IS NULL AND v.datedepartreelle IS NULL THEN 1 ELSE 0 END) AS planifie',
            )
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Voyages EN COURS (non clôturés) dont la ligne dessert la gare donnée, avec un car affecté.
     * Sert au « suivi des cars » du tableau de bord de gare : on récupère les voyages qui passent par
     * la gare, le calcul approche/départ (par rapport à garecourante) se fait ensuite en PHP.
     *
     * @return Voyage[]
     */
    public function enCoursDesservantGare(int $gareId, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.ligne', 'l')
            ->join('l.arrets', 'a') // filtre : la ligne a un arrêt à cette gare (collection NON sélectionnée → reste complète au chargement)
            ->andWhere('a.gare = :gareId')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datearriveereelle IS NULL')   // non clôturé
            ->andWhere('v.car IS NOT NULL')   // un car à suivre
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('gareId', $gareId)
            ->setParameter('ide', $identreprise)
            ->distinct()
            ->orderBy('v.datedepartprevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Voyages NON CLÔTURÉS dont la ligne dessert le tronçon provenance → destination (provenance
     * AVANT destination). Pour le choix du départ côté client.
     *
     * Les voyages DÉJÀ PARTIS ne sont plus exclus : sur une ligne Abidjan → Bouaké → Korhogo, un car
     * qui a quitté Abidjan reste réservable au départ de Bouaké, où il n'est pas encore passé. Le
     * tri se fait à la maille de la GARE DE MONTÉE, ce que le SQL ne sait pas faire ici — c'est
     * DepartsPubliquesProvider qui écarte les départs dont la montée est dépassée ou trop proche.
     * D'où le garde-fou temporel large ci-dessous : borner sur le départ du voyage couperait
     * justement les montées en aval, encore valables plusieurs heures après.
     *
     * @return Voyage[]
     */
    public function findFutursPourTroncon(int $provenanceId, int $destinationId, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.ligne', 'l')->addSelect('l')
            ->join('l.arrets', 'ap', 'WITH', 'ap.gare = :prov')
            ->join('l.arrets', 'ad', 'WITH', 'ad.gare = :dest')
            ->andWhere('ap.ordre < ad.ordre') // provenance avant destination sur la ligne
            /*
                Hydratation des arrêts par une jointure SÉPARÉE et NON filtrée : le provider a besoin
                de la collection COMPLÈTE (ordres, durées, position du car). Ajouter un addSelect sur
                'ap'/'ad' la réduirait aux deux arrêts du filtre — collection tronquée en mémoire.
            */
            ->leftJoin('l.arrets', 'atous')->addSelect('atous')
            ->leftJoin('atous.gare', 'gtous')->addSelect('gtous')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.datearriveereelle IS NULL') // pas clôturé
            ->andWhere('v.datedepartprevue > :depuis') // écarte l'historique, pas les départs du jour
            ->setParameter('prov', $provenanceId)
            ->setParameter('dest', $destinationId)
            ->setParameter('ide', $identreprise)
            ->setParameter('depuis', (new \DateTimeImmutable())->modify('-2 days'))
            ->distinct()
            ->orderBy('v.datedepartprevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Voyages NON CLÔTURÉS d'une entreprise, encore susceptibles d'accueillir une réservation.
     *
     * Le tri fin (car déjà passé à la gare de montée, délai de présentation restant) se fait à la
     * maille de la GARE et dépend des durées d'arrêt : hors de portée du SQL, c'est
     * VoyagesReservablesProvider qui tranche. Ici on ne fait qu'écarter l'historique — d'où la borne
     * temporelle large : couper sur le départ du voyage éliminerait les montées en aval, encore
     * valables plusieurs heures après.
     *
     * @return Voyage[]
     */
    public function findOuvertsPourEntreprise(int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            /*
                Ligne + arrêts + gares HYDRATÉS : l'appelant interroge les arrêts de chaque voyage
                (position du car, durée de trajet). Sans ce fetch-join, chaque voyage déclenchait
                trois requêtes de plus — un N+1 sur toute la liste du sélecteur.
                Jointures NON filtrées : une collection fetch-jointe avec un WITH serait tronquée.
            */
            ->leftJoin('v.ligne', 'l')->addSelect('l')
            ->leftJoin('l.arrets', 'a')->addSelect('a')
            ->leftJoin('a.gare', 'g')->addSelect('g')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.datearriveereelle IS NULL')
            ->andWhere('v.datedepartprevue > :depuis')
            ->setParameter('ide', $identreprise)
            ->setParameter('depuis', (new \DateTimeImmutable())->modify('-2 days'))
            ->orderBy('v.datedepartprevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Voyages (avec ligne, arrêts et gares HYDRATÉS) pour un calcul d'occupation en lot.
     *
     * Une seule requête pour tout le lot : l'occupation s'appuie sur l'ordre des arrêts, qui aurait
     * sinon déclenché trois requêtes par voyage sur une page de statistiques.
     *
     * @param int[] $ids
     * @return Voyage[]
     */
    public function findPourOccupation(array $ids, int $identreprise): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('v')
            ->leftJoin('v.ligne', 'l')->addSelect('l')
            ->leftJoin('l.arrets', 'a')->addSelect('a')
            ->leftJoin('a.gare', 'g')->addSelect('g')
            ->andWhere('v.id IN (:ids)')
            ->andWhere('v.identreprise = :ide')
            ->setParameter('ids', $ids)
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getResult();
    }

    // -- FlotteActivity -- //

    public function countParCar(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->select('IDENTITY(v.car) AS carid, COUNT(v.id) AS nbvoyages')
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->andWhere('v.car IS NOT NULL')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('v.car')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Voyage[] Returns an array of Voyage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Voyage
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
