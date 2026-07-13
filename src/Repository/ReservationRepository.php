<?php

namespace App\Repository;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Réservations qui « tiennent » encore une place sur un voyage — c.-à-d. dont le BILLET n'est pas
     * encore émis (r.ticket IS NULL), NON échues (dateexpiration future = deadline départ − délai) et
     * EN_ATTENTE (place tenue en attente de paiement) OU CONFIRMEE/payée (bon tenu fermement, en
     * attente d'émission du billet). Passé la deadline, ni l'une ni l'autre ne tient plus la place :
     * elle se libère immédiatement pour la revente au guichet (sans attendre le cron d'expiration).
     * Une fois le billet émis (r.ticket renseigné), c'est le ticket VALIDE qui tient la place → exclu
     * ici pour éviter le double-comptage. Sert au calcul de capacité (cf. CapaciteService).
     *
     * @return Reservation[]
     */
    public function findActivesPourVoyage(int $voyageId, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.voyage = :voyage')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.ticket IS NULL')
            ->andWhere('r.dateexpiration > :now')
            ->andWhere('r.statut IN (:actifs)')
            ->setParameter('voyage', $voyageId)
            ->setParameter('ide', $identreprise)
            ->setParameter('actifs', [
                ReservationStatus::STATUT_EN_ATTENTE->value,
                ReservationStatus::STATUT_CONFIRMEE->value,
            ])
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    public function countForEntreprise(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.identreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Passe EXPIREE toutes les réservations échues (dateexpiration dépassée) dont le billet n'est pas
     * émis : EN_ATTENTE (jamais payées) ET CONFIRMEE/payées non retirées (no-show — l'argent est
     * FORFAIT, non remboursable ; l'état de paiement PAYE est conservé pour distinguer un no-show
     * payé d'un simple abandon non payé). La place est de toute façon déjà libérée côté capacité
     * (findActivesPourVoyage ignore les échues). Bulk UPDATE.
     *
     * @return int nombre de réservations expirées
     */
    /**
     * Réservations d'un client (par TÉLÉPHONE) dans une entreprise — historique invité, plus récentes
     * d'abord. Le contact est normalisé (espaces retirés) des deux côtés pour matcher malgré le format.
     *
     * @return Reservation[]
     */
    public function findParContactPourEntreprise(string $contact, int $identreprise, int $limit = 50): array
    {
        $contact = trim($contact);
        if ($contact === '') {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.contactclient = :contact')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('ide', $identreprise)
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getResult();
    }

    // ─────────── Statistiques (par période) ─────────── //

    /** Comptes + recette (payée) par statut sur la période. @return array<int, array{statut:string, total:int, recette:int}> */
    public function statsParStatut(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.statut AS statut, COUNT(r.id) AS total, COALESCE(SUM(CASE WHEN r.etatpaiement = :paye THEN r.prix + r.penalitemontant ELSE 0 END), 0) AS recette')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->groupBy('r.statut')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();
    }

    /** Comptes par canal (GUICHET/MOBILE). @return array<int, array{source:string, total:int}> */
    public function statsParSource(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.source AS source, COUNT(r.id) AS total')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->groupBy('r.source')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();
    }

    /** Nombre de réservations dont le billet a été émis (place honorée) sur la période. */
    public function countBilletsEmis(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.ticket IS NOT NULL')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * No-shows PAYÉS : réservations payées dont le client a manqué le départ — à régulariser
     * (récupérables) OU définitivement perdues (fenêtre de régularisation dépassée).
     */
    public function countNoShows(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.statut IN (:noShowStatuts)')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('noShowStatuts', [
                ReservationStatus::STATUT_A_REGULARISER->value,
                ReservationStatus::STATUT_EXPIREE->value,
            ])
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * « Bons de réservation encaissés, non encore émis en billet » : réservations PAYÉES (argent sur le
     * compte admin) dont le billet n'est pas encore matérialisé (r.ticket IS NULL) et encore émissible
     * (CONFIRMEE ou A_REGULARISER). C'est un produit constaté d'AVANCE : il ne fait PAS partie de la
     * recette billets tant que le billet n'est pas émis (on l'expose à part dans la caisse). Période sur
     * la date de paiement.
     *
     * @return array{count:int, montant:int}
     */
    public function encaissementsReservationNonEmis(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS count, COALESCE(SUM(r.prix), 0) AS montant')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.ticket IS NULL')
            ->andWhere('r.statut IN (:emissibles)')
            ->andWhere('r.datepaiement BETWEEN :debut AND :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('emissibles', [
                ReservationStatus::STATUT_CONFIRMEE->value,
                ReservationStatus::STATUT_A_REGULARISER->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult();

        return [
            'count' => (int) ($row['count'] ?? 0),
            'montant' => (int) ($row['montant'] ?? 0),
        ];
    }

    /**
     * Recette RÉSERVATION reconnue AU PAIEMENT (option 2) : SUM des réservations payées (etatpaiement=PAYE)
     * sur la période de PAIEMENT (datepaiement). L'émission du billet ne crée pas de recette (anti
     * double-comptage : les billets de réservation sont exclus des recettes tickets). Inclut les no-shows
     * payés (A_REGULARISER/EXPIREE) : l'argent est encaissé. Inclut aussi la PÉNALITÉ de no-show
     * (penalitemontant, encaissée physiquement au guichet à la régularisation) : c'est de la recette réelle.
     */
    public function recettesPayees(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.prix + r.penalitemontant), 0) AS total')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.datepaiement >= :debut')
            ->andWhere('r.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($row ?? 0), 2);
    }

    /**
     * Recette RÉSERVATION payée par GARE DE PROVENANCE (r.gare = gare de montée = gare qui a initié la
     * réservation). Reconnue au paiement (datepaiement). @return array<int, array{gareid:int, garelibelle:string, nbreservations:int, recette:int}>
     */
    public function recettePayeeParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(r.id) AS nbreservations, COALESCE(SUM(r.prix + r.penalitemontant), 0) AS recette')
            ->join('r.gare', 'g')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.datepaiement >= :debut')
            ->andWhere('r.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette RÉSERVATION payée PAR JOUR (sur datepaiement) — pour la série temporelle financière.
     * @return array<int, array{label:string, montant:int}>
     */
    public function recettesPayeesParJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('DATE(r.datepaiement) AS label, COALESCE(SUM(r.prix + r.penalitemontant), 0) AS montant')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.datepaiement >= :debut')
            ->andWhere('r.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Recette + nombre de RÉSERVATIONS payées regroupées par LIGNE (via le voyage réservé), reconnues au
     * paiement (datepaiement) — complète le « détail par ligne » (billets directs + réservations).
     * @return array<int, array{ligneid:int, nbreservations:int, recette:int}>
     */
    public function recettesPayeesParLigne(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('l.id AS ligneid, COUNT(r.id) AS nbreservations, COALESCE(SUM(r.prix + r.penalitemontant), 0) AS recette')
            ->join('r.voyage', 'v')
            ->join('v.ligne', 'l')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('r.datepaiement >= :debut')
            ->andWhere('r.datepaiement <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Réservations PAYÉES ventilées par gare de DÉPART EFFECTIVE du voyage (v.gareprovenance) avec l'origine
     * de la ligne, pour la vue « départs effectifs » (complets vs partiels). Reconnue sur la période de
     * DÉPART du voyage (v.datedepartprevue), cohérente avec la recette billets des départs.
     * @return array<int, array{gareid:int, libelle:string, ville:?string, origineid:int, nbreservations:int, recette:int}>
     */
    public function departsPayesParGareProvenance(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('r')
            ->select('go.id AS gareid, go.libelle AS libelle, gv.nom AS ville, lo.id AS origineid, COUNT(r.id) AS nbreservations, COALESCE(SUM(r.prix + r.penalitemontant), 0) AS recette')
            ->join('r.voyage', 'v')
            ->join('v.gareprovenance', 'go')
            ->leftJoin('go.ville', 'gv')
            ->join('v.ligne', 'l')
            ->join('l.gareorigine', 'lo')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.etatpaiement = :paye')
            ->andWhere('v.datedepartprevue >= :debut')
            ->andWhere('v.datedepartprevue <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('paye', 'PAYE')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('go.id')
            ->addGroupBy('lo.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Trajets les plus réservés sur la période. @return array<int, array{montee:string, descente:string, total:int}> */
    public function topTrajets(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->select('gm.libelle AS montee, gd.libelle AS descente, COUNT(r.id) AS total')
            ->join('r.gare', 'gm')
            ->join('r.garedescente', 'gd')
            ->andWhere('r.identreprise = :ide')
            ->andWhere('r.deletedAt IS NULL')
            ->andWhere('r.createdAt BETWEEN :debut AND :fin')
            ->groupBy('gm.id')
            ->addGroupBy('gd.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Réservations IMPAYÉES (EN_ATTENTE) échues → EXPIREE (définitivement perdues, aucun engagement
     * financier). Le billet ne doit pas déjà être émis.
     */
    public function expirerEnAttenteEchues(): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.statut', ':expiree')
            ->set('r.updatedAt', ':now')
            ->where('r.statut = :enAttente')
            ->andWhere('r.ticket IS NULL')
            ->andWhere('r.dateexpiration <= :now')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('expiree', ReservationStatus::STATUT_EXPIREE->value)
            ->setParameter('enAttente', ReservationStatus::STATUT_EN_ATTENTE->value)
            ->setParameter('now', new \DateTimeImmutable(), \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Réservations PAYÉES (CONFIRMEE) échues sans billet émis → A_REGULARISER : le no-show n'est plus
     * perdu, le client pourra régulariser (report + pénalité) tant que la fenêtre n'est pas dépassée.
     */
    public function basculerConfirmeesEnRegularisation(): int
    {
        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.statut', ':aRegulariser')
            ->set('r.updatedAt', ':now')
            ->where('r.statut = :confirmee')
            ->andWhere('r.ticket IS NULL')
            ->andWhere('r.dateexpiration <= :now')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('aRegulariser', ReservationStatus::STATUT_A_REGULARISER->value)
            ->setParameter('confirmee', ReservationStatus::STATUT_CONFIRMEE->value)
            ->setParameter('now', new \DateTimeImmutable(), \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Réservations A_REGULARISER dont la fenêtre de régularisation (départ prévu + N jours) est dépassée.
     * La fenêtre étant configurable par entreprise, l'appelant tranche le sort de chacune.
     *
     * @return Reservation[]
     */
    public function findARegulariser(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statut = :aRegulariser')
            ->andWhere('r.ticket IS NULL')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('aRegulariser', ReservationStatus::STATUT_A_REGULARISER->value)
            ->getQuery()
            ->getResult();
    }
}
