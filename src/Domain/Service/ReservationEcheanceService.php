<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Gare;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Repository\ReservationRepository;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;

/**
 * SOURCE UNIQUE du calcul des échéances de réservation.
 *
 * Une réservation porte UNE date d'échéance ('dateexpiration') dont le SENS dépend de son statut :
 *  - impayée (EN_ATTENTE) : limite de PAIEMENT, comptée depuis la création et bornée par la présentation ;
 *  - payée (CONFIRMEE)    : limite de PRÉSENTATION au guichet (au-delà → no-show à régulariser).
 *
 * Cette règle était appliquée à la création puis au paiement ; la replanification d'un départ en a
 * ajouté un troisième usage. Elle est donc centralisée ici pour ne pas diverger.
 *
 * Toutes les échéances se comptent depuis l'HEURE DE PASSAGE DU CAR À LA GARE DE MONTÉE, et non
 * depuis le départ du voyage : sur une ligne Abidjan → Bouaké → Korhogo, un passager qui monte à
 * Bouaké n'a rien à faire au guichet à l'heure où le car quitte Abidjan. Cette heure est la SOMME des
 * 'Arret::dureeTronconMinutes' de l'origine effective à la gare de montée ; tant qu'une ligne ne les
 * renseigne pas (tout-ou-rien), on retombe sur le départ du voyage — l'ancien comportement.
 */
class ReservationEcheanceService
{
    public function __construct(
        private ReservationConfigService $config,
        private ReservationRepository $reservationRepository,
        private EntityManagerInterface $em,
        private VoyageGuard $voyageGuard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    /**
     * Heure à laquelle le car est attendu à $gare.
     *
     * Chaque arrêt porte 'dureeTronconMinutes', la durée du SEGMENT QUI MÈNE À LUI (NULL à
     * l'origine de la ligne) — et non un cumul depuis l'origine. Le décalage est donc la SOMME des
     * tronçons parcourus.
     *
     * 'datedepartprevue' est l'heure de départ de l'ORIGINE EFFECTIVE DU VOYAGE, qui diffère de
     * celle de la ligne sur un DÉPART PARTIEL : quand Bouaké crée un voyage sur Abidjan → Korhogo,
     * c'est Bouaké qui devient l'origine, et le départ annoncé est le sien. On ne somme donc que
     * les tronçons situés APRÈS cette origine effective :
     *
     *   Ligne Abidjan(—) → Bouaké(+240) → Korhogo(+180)
     *
     *   Départ NORMAL d'Abidjan à 08:00        Départ PARTIEL de Bouaké à 08:00
     *     Bouaké  : 08:00 + 240      = 12:00     Bouaké  : 08:00 + 0        = 08:00
     *     Korhogo : 08:00 + 240 + 180 = 15:00    Korhogo : 08:00 + 180      = 11:00
     *
     * Repli sur le départ du voyage si la ligne ne renseigne pas ses durées (ou si la gare n'est pas
     * un de ses arrêts) : c'est le comportement historique, approximatif mais jamais plus permissif.
     */
    public function heurePassage(Voyage $voyage, ?Gare $gare): ?\DateTimeImmutable
    {
        $depart = $voyage->getDatedepartprevue();
        if ($depart === null) {
            return null;
        }

        $ligne = $voyage->getLigne();
        if ($ligne === null || $gare === null) {
            return $depart;
        }

        // Arrêts ordonnés, avec repérage de l'origine EFFECTIVE du voyage et de la gare visée.
        $arrets = $ligne->getArrets()->toArray();
        usort($arrets, static fn ($a, $b) => (int) $a->getOrdre() <=> (int) $b->getOrdre());

        $origine = $voyage->getOrigineEffective();
        $ordreOrigine = null;
        $ordreGare = null;
        foreach ($arrets as $arret) {
            $gareArret = $arret->getGare();
            if ($gareArret === null) {
                continue;
            }
            if ($origine !== null && $gareArret->getId() === $origine->getId()) {
                $ordreOrigine = (int) $arret->getOrdre();
            }
            if ($gareArret->getId() === $gare->getId()) {
                $ordreGare = (int) $arret->getOrdre();
            }
        }
        if ($ordreGare === null) {
            return $depart; // gare hors de la ligne
        }
        $ordreOrigine ??= 0; // repli : origine = premier arrêt

        /*
            Décalage = SOMME des tronçons de l'origine effective (exclue) jusqu'à la gare (incluse) —
            chaque arrêt porte le tronçon qui MÈNE à lui. Décalage ≤ 0 = gare AVANT l'origine effective :
            le car n'y passe pas (vente/réservation déjà refusées), on renvoie le départ plutôt qu'une
            heure antidatée. Un tronçon manquant (ligne non renseignée, tout-ou-rien) → repli sur le
            départ : jamais une échéance faussement permissive.
        */
        if ($ordreGare <= $ordreOrigine) {
            return $depart;
        }
        $decalage = 0;
        foreach ($arrets as $arret) {
            $o = (int) $arret->getOrdre();
            if ($o > $ordreOrigine && $o <= $ordreGare) {
                $troncon = $arret->getDureeTronconMinutes();
                if ($troncon === null) {
                    return $depart;
                }
                $decalage += $troncon;
            }
        }

        return $decalage <= 0 ? $depart : $depart->modify('+' . $decalage . ' minutes');
    }

    /**
     * Retard COURANT du car, en minutes (positif = en retard, négatif = en avance), mesuré à sa
     * position actuelle ('garecourante') : heure RÉELLE de passage à cette gare − heure PRÉVUE.
     *
     * L'heure réelle est l'arrivée horodatée à la gare courante (cf. entité Passage) ; à l'origine,
     * où il n'y a pas d'arrivée, on prend le départ réel du voyage. Renvoie null tant qu'aucun
     * horodatage réel n'est disponible à la position courante (voyage pas encore parti, ou position
     * inconnue) ou si l'heure prévue n'est pas calculable — jamais un retard inventé.
     */
    public function retardCourantMinutes(Voyage $voyage): ?int
    {
        $gare = $voyage->getGarecourante();
        if ($gare === null) {
            return null;
        }

        // Heure réelle connue à la gare courante : arrivée réelle, sinon départ réel (passage en cours).
        $reel = null;
        foreach ($voyage->getPassages() as $passage) {
            if ($passage->getGare()?->getId() === $gare->getId()) {
                $reel = $passage->getArriveeReelle() ?? $passage->getDepartReelle();
                break;
            }
        }
        // Repli origine : le départ réel du voyage vaut passage (départ) à l'origine effective.
        if ($reel === null) {
            $origine = $voyage->getOrigineEffective();
            if ($origine !== null && $gare->getId() === $origine->getId()) {
                $reel = $voyage->getDatedepartreelle();
            }
        }
        if ($reel === null) {
            return null;
        }

        $prevu = $this->heurePassage($voyage, $gare);
        if ($prevu === null) {
            return null;
        }

        return (int) round(($reel->getTimestamp() - $prevu->getTimestamp()) / 60);
    }

    /**
     * Limite de PRÉSENTATION au guichet — c'est aussi la limite au-delà de laquelle on ne réserve
     * plus. $passage est l'heure attendue du car À LA GARE DE MONTÉE (cf. heurePassage).
     */
    public function limitePresentation(\DateTimeImmutable $passage, int $entrepriseId): \DateTimeImmutable
    {
        return $passage->modify('-' . $this->config->getParametre($entrepriseId)->getDelaiPresentationMinutes() . ' minutes');
    }

    /** Idem, à partir du voyage et de la gare de montée. Null si le voyage n'a pas de date de départ. */
    public function limitePresentationPour(Voyage $voyage, ?Gare $montee, int $entrepriseId): ?\DateTimeImmutable
    {
        $passage = $this->heurePassage($voyage, $montee);

        return $passage === null ? null : $this->limitePresentation($passage, $entrepriseId);
    }

    /**
     * Limite de PAIEMENT d'une réservation impayée : délai compté depuis $depuis (sa création), et
     * jamais au-delà de la limite de présentation — inutile de pouvoir payer une fois le car passé.
     */
    public function limitePaiement(\DateTimeImmutable $depuis, \DateTimeImmutable $passage, int $entrepriseId): \DateTimeImmutable
    {
        $paiement = $depuis->modify('+' . $this->config->getParametre($entrepriseId)->getDelaiPaiementMinutes() . ' minutes');
        $presentation = $this->limitePresentation($passage, $entrepriseId);

        return $paiement < $presentation ? $paiement : $presentation;
    }

    /** Échéance qui s'applique à CETTE réservation, selon son statut et sa gare de montée. */
    public function echeanceApplicable(Reservation $reservation, Voyage $voyage): \DateTimeImmutable
    {
        $entrepriseId = (int) $reservation->getIdentreprise();
        $passage = $this->heurePassage($voyage, $reservation->getGare())
            ?? $voyage->getDatedepartprevue()
            ?? new \DateTimeImmutable();

        if ($reservation->getStatut() === ReservationStatus::STATUT_CONFIRMEE->value) {
            return $this->limitePresentation($passage, $entrepriseId);
        }

        return $this->limitePaiement(
            $reservation->getCreatedAt() ?? new \DateTimeImmutable(),
            $passage,
            $entrepriseId
        );
    }

    /**
     * Recalcule les échéances des réservations vivantes d'un voyage dont la DATE DE DÉPART a changé.
     *
     * Sans cela, l'échéance resterait calée sur l'ancienne date : un report du départ aurait déclaré
     * no-show des clients qui avaient payé (le car n'était même pas parti), et une avance du départ
     * aurait laissé des réservations « valides » après le départ réel.
     *
     * DÉPART AVANCÉ : recaler l'échéance ne suffit pas. Le client payé s'était organisé sur l'horaire
     * annoncé ; en avançant, la compagnie raccourcit — voire supprime — sa fenêtre de présentation.
     * S'il manque le car, le no-show est du fait de la COMPAGNIE : on l'exonère de la pénalité de
     * report (cf. Reservation::$penaliteexoneree). On ne restreint pas ce marquage aux échéances déjà
     * dépassées : « avancé de 3 h, prévenu 20 min avant » est tout aussi subi, et une règle simple
     * s'explique au guichet.
     *
     * @param ?\DateTimeInterface $ancienDepart date de départ AVANT modification, quand l'appelant la
     *                                          connaît — seul moyen de savoir si le départ a été
     *                                          avancé (donc s'il y a lieu d'exonérer).
     * @param bool $flush false quand l'APPELANT maîtrise déjà la persistance (cas d'un processor
     *                    ApiPlatform : les réservations sont managées, elles seront écrites par le
     *                    flush final, EN MÊME TEMPS que le voyage). Flusher ici couperait l'écriture
     *                    en deux : un contrôle qui échoue ensuite (car indisponible…) laisserait la
     *                    nouvelle date déjà enregistrée alors que la requête a été refusée.
     * DÉPART REPORTÉ : symétriquement, les no-show (A_REGULARISER) dont la NOUVELLE échéance
     * retombe dans le futur sont REPÊCHÉS en CONFIRMEE — ils ne l'étaient devenus que contre
     * l'ancien horaire (cf. bloc « repêchage » plus bas, et sa conséquence sur la capacité).
     *
     * @return int nombre de réservations touchées (replanifiées + no-show repêchés)
     */
    public function replanifierPourVoyage(Voyage $voyage, ?\DateTimeInterface $ancienDepart = null, bool $flush = true): int
    {
        $depart = $voyage->getDatedepartprevue();
        if ($depart === null || $voyage->getId() === null) {
            return 0;
        }
        $avance = $ancienDepart !== null && $depart->getTimestamp() < $ancienDepart->getTimestamp();
        $now = new \DateTimeImmutable();

        $reservations = $this->reservationRepository->findVivantesPourVoyage($voyage->getId());
        foreach ($reservations as $reservation) {
            $echeanceAvant = $reservation->getDateexpiration();
            $reservation->setDateexpiration($this->echeanceApplicable($reservation, $voyage));

            /*
                Exonération réservée aux PAYÉES (une impayée qui expire ne subit aucune pénalité, elle
                perd juste sa place) ET à celles dont l'échéance courait ENCORE au moment du
                changement : celui qui était déjà no-show avant qu'on touche à l'horaire l'est de son
                propre fait, l'avance ne doit pas lui offrir une remise rétroactive.
            */
            if ($avance
                && $reservation->getStatut() === ReservationStatus::STATUT_CONFIRMEE->value
                && ($echeanceAvant === null || $echeanceAvant > $now)
            ) {
                $reservation->setPenaliteexoneree(true);
            }
        }

        /*
            REPÊCHAGE DES NO-SHOW. Une réservation payée passée en A_REGULARISER l'a été parce que
            SON échéance de présentation — calculée sur l'ANCIENNE date de départ — était dépassée.
            Si la compagnie replanifie le départ et que la NOUVELLE échéance retombe dans le futur,
            ce no-show n'en est plus un : le car n'est pas parti, le client a payé, et c'est
            l'horaire qui a bougé. On le remet donc en CONFIRMEE, sans pénalité ni report à faire.

            La condition « nouvelle échéance dans le futur » suffit à couvrir les deux sens : un
            départ AVANCÉ ne peut que rapprocher l'échéance, donc ne repêche personne.

            CONSÉQUENCE ASSUMÉE : A_REGULARISER ne tient PLUS de place (cf. ReservationStatus::
            tenantsPlace) ; repasser en CONFIRMEE en REPREND une, qui a pu être revendue entre-temps
            — c'est le surbooking assumé du modèle, préférable à laisser un client payant no-show
            d'un retard décidé par la compagnie.
        */
        $repeches = [];
        foreach ($this->reservationRepository->findARegulariserPourVoyage($voyage->getId()) as $reservation) {
            $presentation = $this->limitePresentationPour(
                $voyage,
                $reservation->getGare(),
                (int) $reservation->getIdentreprise()
            );
            // Nouvelle échéance toujours dépassée → le no-show reste acquis.
            if ($presentation === null || $presentation <= $now) {
                continue;
            }
            $reservation
                ->setStatut(ReservationStatus::STATUT_CONFIRMEE->value)
                ->setDateexpiration($presentation);
            $repeches[] = $reservation;

            // Traçable : ce repêchage rend une place à un client ET la REPREND sur le voyage.
            $this->activiteLogger->log(
                ActiviteLogger::RESERVATION_REPECHEE,
                sprintf(
                    'Réservation %s repêchée (no-show levé) : départ replanifié, présentation reportée au %s',
                    $reservation->getCode(),
                    $presentation->format('d/m/Y H:i')
                ),
                'Reservation',
                $reservation->getId()
            );
        }

        if ($flush && ($reservations !== [] || $repeches !== [])) {
            $this->em->flush();
        }

        return count($reservations) + count($repeches);
    }

    /**
     * CLÔTURE les réservations dont le car a quitté la gare de MONTÉE (départ réel de l'origine, ou
     * réception en aval qui fait avancer la position du véhicule).
     *
     * Le départ réel était jusqu'ici invisible pour la réservation : il ne propageait qu'aux courriers
     * et aux bagages. Tant que la date de départ PRÉVUE restait dans le futur — un car qui part en
     * avance sans qu'on rééchelonne le planning — on pouvait encore réserver ET payer une place sur un
     * véhicule déjà parti, et ces places restaient tenues, donc invendables au guichet.
     *
     * Les réservations dont la montée est en AVAL de la position ne sont PAS touchées : le car va
     * encore passer les chercher. C'est aussi pourquoi on ne peut pas se contenter de
     * 'datedepartreelle' — il ne dit rien des gares intermédiaires (cf. VoyageGuard::monteeDepassee).
     *
     * Ne flushe pas : les appelants (départ réel, réception) écrivent déjà dans la même unité de
     * travail que le voyage.
     *
     * @return int nombre de réservations clôturées
     */
    public function cloturerMonteesDepassees(Voyage $voyage, ?\DateTimeImmutable $instant = null): int
    {
        if ($voyage->getId() === null) {
            return 0;
        }
        $instant ??= new \DateTimeImmutable();

        $cloturees = 0;
        foreach ($this->reservationRepository->findVivantesPourVoyage($voyage->getId()) as $reservation) {
            if (!$this->voyageGuard->monteeDepassee($voyage, $reservation->getGare())) {
                continue;
            }
            // Déjà échue : le no-show est acquis et antérieur au passage du car — ni à reprendre, ni
            // à exonérer, sans quoi un départ tardif effacerait la pénalité d'un vrai absent.
            $echeance = $reservation->getDateexpiration();
            if ($echeance !== null && $echeance <= $instant) {
                continue;
            }

            // Le car est parti AVANT l'échéance annoncée au client : ce n'est pas lui qui a manqué.
            if ($reservation->getStatut() === ReservationStatus::STATUT_CONFIRMEE->value) {
                $reservation->setPenaliteexoneree(true);
            }
            $reservation->setDateexpiration($instant);
            $cloturees++;
        }

        return $cloturees;
    }
}
