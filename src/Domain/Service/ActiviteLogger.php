<?php

namespace App\Domain\Service;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journalise « qui a fait quoi » : crée une ligne 'Activite'. On se contente de persist() — le flush
 * est porté par le processor appelant (appeler log() AVANT son flush final). Scopé à l'entreprise de
 * l'acteur courant ; ne loggue rien hors contexte utilisateur (ex. CLI).
 *
 * RÈGLE : une trace qui échoue ne fait JAMAIS échouer le geste. Journaliser est un service rendu à
 * l'exploitation, pas une condition de la vente, du désistement ou de la clôture. 'log()' ne laisse
 * donc remonter aucune exception : ce qui casse ici part dans le logger applicatif et le métier
 * continue. Une activité perdue se constate ; une vente refusée parce que sa trace n'a pas pu
 * s'écrire est une panne.
 *
 * La trace reste néanmoins ATOMIQUE avec le geste, et c'est voulu : elle part dans la MÊME
 * transaction, donc si le geste échoue, le journal ne raconte pas quelque chose qui n'a pas eu lieu.
 * Sortir l'écriture de la transaction rendrait bien l'INSERT inoffensif, mais au prix de cette
 * propriété — un journal qui atteste une vente annulée en cours de route serait pire qu'un journal
 * absent. On garde donc l'atomicité, et on retire à l'INSERT ses raisons d'échouer : toutes les
 * valeurs sont BORNÉES ci-dessous aux limites réelles des colonnes.
 */
class ActiviteLogger
{
    // Types d'événements (pour icône/filtre côté front)
    public const VOYAGE_CAR = 'VOYAGE_CAR';
    public const VOYAGE_COMMERCIAL = 'VOYAGE_COMMERCIAL';
    public const VOYAGE_POSITION = 'VOYAGE_POSITION';
    public const VOYAGE_RECEPTION = 'VOYAGE_RECEPTION';
    public const VOYAGE_DEPART = 'VOYAGE_DEPART';
    public const VOYAGE_CLOTURE = 'VOYAGE_CLOTURE';
    public const VOYAGE_PERSONNEL = 'VOYAGE_PERSONNEL';

    // Billetterie / courrier / bagage — événements critiques (annulation, suppression, perte, livraison)
    public const TICKET_ANNULE = 'TICKET_ANNULE';
    public const TICKET_REPORTE = 'TICKET_REPORTE';
    public const TICKET_REPORTE_EVICTION = 'TICKET_REPORTE_EVICTION'; // report d'un billet évincé (imputable compagnie, pas au client)
    public const TICKET_SUPPRIME = 'TICKET_SUPPRIME';
    public const TICKET_REMISE = 'TICKET_REMISE'; // remise appliquée sur un billet (audit anti-abus)
    public const COURRIER_ANNULE = 'COURRIER_ANNULE';
    public const COURRIER_LIVRE = 'COURRIER_LIVRE';
    public const COURRIER_PERDU = 'COURRIER_PERDU';
    public const COURRIER_SUPPRIME = 'COURRIER_SUPPRIME';
    public const COLIS_PERDU = 'COLIS_PERDU';
    public const BAGAGE_PERDU = 'BAGAGE_PERDU';
    public const BAGAGE_ANNULE = 'BAGAGE_ANNULE';
    public const BAGAGE_SUPPRIME = 'BAGAGE_SUPPRIME';
    public const BAGAGE_MONTANT_FORCE = 'BAGAGE_MONTANT_FORCE'; // montant forcé ≠ tarif (audit anti sous-déclaration)

    // Réservation — événements qui changent le SORT d'une place déjà payée
    public const RESERVATION_REGULARISEE = 'RESERVATION_REGULARISEE'; // no-show reporté sur un autre départ (pénalité)
    public const RESERVATION_REPECHEE = 'RESERVATION_REPECHEE';       // no-show rendu CONFIRMEE par une replanification du départ
    public const RESERVATION_ANNULEE = 'RESERVATION_ANNULEE';         // annulation d'une réservation en attente (place rendue)
    public const RESERVATION_CONFIRMEE = 'RESERVATION_CONFIRMEE';     // encaissement au guichet (de l'argent est entré)
    public const RESERVATION_BILLET_EMIS = 'RESERVATION_BILLET_EMIS'; // bon transformé en billet (la réservation est honorée)

    /*
        Flotte — immobilisations. Le statut d'un car ne change QUE par affectation à un voyage (déjà
        tracée par VOYAGE_CAR) ou par dépannage : ces trois types couvrent donc toute l'indisponibilité
        d'un véhicule, sans doublonner l'exploitation.
    */
    public const DEPANNAGE_OUVERT = 'DEPANNAGE_OUVERT';
    public const DEPANNAGE_CLOTURE = 'DEPANNAGE_CLOTURE';
    public const DEPANNAGE_ANNULE = 'DEPANNAGE_ANNULE';

    // Stock — le registre 'Inventaire' porte déjà qui/quoi/combien ; l'activité porte le POURQUOI
    public const STOCK_AJUSTE = 'STOCK_AJUSTE';

    /** Longueurs des colonnes 'activite' : au-delà, l'INSERT serait refusé par la base. */
    private const MAX_TYPE = 60;
    private const MAX_LIBELLE = 255;
    private const MAX_CIBLETYPE = 50;

    /** Borne de l'INT MySQL de 'cibleid' : un identifiant plus grand ferait échouer l'insertion. */
    private const MAX_CIBLEID = 2147483647;

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private LoggerInterface $logger
    )
    {
    }

    public function log(string $type, string $libelle, ?string $cibletype = null, ?int $cibleid = null): void
    {
        try {
            $user = $this->security->getUser();
            if(!$user instanceof User) {
                return;
            }
            $entrepriseId = $user->getEntreprise()?->getId();
            if($entrepriseId === null) {
                return;
            }
            $activite = (new Activite())
                ->setType(mb_substr($type, 0, self::MAX_TYPE))
                ->setLibelle(mb_substr($libelle, 0, self::MAX_LIBELLE))
                ->setCibletype($cibletype !== null ? mb_substr($cibletype, 0, self::MAX_CIBLETYPE) : null)
                ->setCibleid($this->identifiantExploitable($cibleid))
                ->setAuteur($user)
                ->setIdentreprise($entrepriseId)
                ->setCreatedBy($user->getId())
            ;
            $this->em->persist($activite);
        } catch (\Throwable $e) {
            $this->signaler($e, $type, $cibletype, $cibleid);
        }
    }

    /** Un identifiant hors de l'INT MySQL n'est pas exploitable : mieux vaut nul qu'un INSERT refusé. */
    private function identifiantExploitable(?int $cibleid): ?int
    {
        return $cibleid !== null && $cibleid > 0 && $cibleid <= self::MAX_CIBLEID ? $cibleid : null;
    }

    /**
     * Une trace perdue se constate dans les logs ; une vente refusée parce que son journal n'a pas pu
     * s'écrire est une panne. On rapporte, on ne propage jamais.
     */
    private function signaler(\Throwable $e, string $type, ?string $cibletype, ?int $cibleid): void
    {
        $this->logger->error('Activité non journalisée : {message}', [
            'message' => $e->getMessage(),
            'type' => $type,
            'cibletype' => $cibletype,
            'cibleid' => $cibleid,
            'exception' => $e
        ]);
    }

    /** Raccourci pour un événement rattaché à un voyage. */
    public function voyage(string $type, string $libelle, int $voyageId): void
    {
        $this->log($type, $libelle, 'Voyage', $voyageId);
    }
}
