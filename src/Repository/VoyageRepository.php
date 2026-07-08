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
     * Voyages À VENIR (départ prévu futur, ni partis ni clôturés) dont la ligne dessert le tronçon
     * provenance → destination (provenance AVANT destination). Pour le choix du départ côté client.
     *
     * @return Voyage[]
     */
    public function findFutursPourTroncon(int $provenanceId, int $destinationId, int $identreprise): array
    {
        return $this->createQueryBuilder('v')
            ->join('v.ligne', 'l')
            ->join('l.arrets', 'ap', 'WITH', 'ap.gare = :prov')
            ->join('l.arrets', 'ad', 'WITH', 'ad.gare = :dest')
            ->andWhere('ap.ordre < ad.ordre') // provenance avant destination sur la ligne
            ->andWhere('v.identreprise = :ide')
            ->andWhere('v.deletedAt IS NULL')
            ->andWhere('v.datedepartreelle IS NULL')  // pas encore parti
            ->andWhere('v.datearriveereelle IS NULL') // pas clôturé
            ->andWhere('v.datedepartprevue > :now')   // départ prévu futur
            ->setParameter('prov', $provenanceId)
            ->setParameter('dest', $destinationId)
            ->setParameter('ide', $identreprise)
            ->setParameter('now', new \DateTimeImmutable())
            ->distinct()
            ->orderBy('v.datedepartprevue', 'ASC')
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
