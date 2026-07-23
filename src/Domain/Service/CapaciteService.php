<?php

namespace App\Domain\Service;

use App\Domain\Enum\TicketStatus;
use App\Entity\Ligne;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Capacité en PLACES d'un voyage par tronçon, partagée entre la vente de billets et la réservation.
 *
 * Une place est TENUE par :
 *  - un billet VALIDE (émis) — elle est physiquement occupée ;
 *  - une réservation encore vivante (sans billet, non échue), qu'elle soit PAYÉE (garantie jusqu'à
 *    l'heure de présentation) ou EN ATTENTE (hold court, le temps de payer). Cf. ReservationStatus.
 *
 * Autrement dit, on ne vend plus par-dessus une réservation : c'est ce qui évite d'encaisser un client
 * à qui aucun billet ne pourra être émis. Un hold impayé se libère seul à son échéance.
 *
 * La capacité effective est celle du car affecté (nb de sièges) ou, à défaut, la capacité prévisionnelle
 * du voyage ('placesprevues') tant qu'aucun car n'est affecté.
 *
 * PRIORITÉ ABSOLUE À LA GARE AMONT : la disponibilité se juge AU POINT DE MONTÉE de l'acheteur, pas sur
 * l'ensemble de son trajet. Une vente depuis une gare en aval ne lui coûte donc rien, quitte à ce que le
 * tronçon commun parte en SURBOOKING : la gare aval dépossédée de ses sièges ouvre un voyage
 * supplémentaire. C'est un choix d'exploitation assumé, pas une approximation.
 *
 * {@see occupationMaximale} répond, elle, à une tout autre question — quelle taille de véhicule
 * honorerait TOUT ce qui est vendu — et raisonne bien sur l'ensemble des segments.
 */
class CapaciteService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private ReservationRepository $reservationRepository
    )
    {
    }

    /** Capacité effective : sièges du car si affecté, sinon capacité prévisionnelle (null si aucune). */
    public function capaciteEffective(Voyage $voyage): ?int
    {
        $car = $voyage->getCar();
        if ($car !== null && $car->getNbrsiege() !== null) {
            return (int) $car->getNbrsiege();
        }

        return $voyage->getPlacesprevues();
    }

    /**
     * Places encore disponibles pour qui embarque à $ordreMontee.
     *
     * PRIORITÉ ABSOLUE À LA GARE AMONT : seules comptent les places tenues par des passagers DÉJÀ à
     * bord au point de montée. Ce qui est vendu depuis une gare en AVAL ne réduit pas la disponibilité
     * de l'amont — quitte à ce que le tronçon commun se retrouve en surbooking : la gare aval qui perd
     * ses sièges ouvre un voyage supplémentaire pour ses passagers.
     *
     * On ne prend donc PAS l'occupation maximale le long du trajet de l'acheteur : cela reviendrait à
     * laisser une vente en aval bloquer l'amont, exactement ce que la règle refuse.
     *
     * $ordreDescente ne participe plus au décompte ; il reste au contrat pour valider le tronçon chez
     * les appelants et pour ne pas casser leur signature.
     */
    public function placesDisponibles(
        Voyage $voyage,
        int $ordreMontee,
        int $ordreDescente,
        int $identreprise,
        ?int $exclureReservationId = null
    ): int
    {
        $capacite = $this->capaciteEffective($voyage);
        if ($capacite === null) {
            return 0;
        }

        return max(0, $capacite - $this->occupationAu(
            $ordreMontee,
            $this->intervallesOccupation($voyage, $identreprise, $exclureReservationId)
        ));
    }

    /**
     * Nombre de réservations EN ATTENTE DE PAIEMENT sur le tronçon — pour informer l'agent de ce qui
     * est en train de se jouer, PAS pour l'autoriser à vendre par-dessus : depuis que le hold de
     * paiement existe, ces places sont bel et bien TENUES (cf. ReservationStatus::tenantsPlace) et
     * déjà déduites par placesDisponibles(). Elles se libèrent seules à l'échéance du paiement.
     *
     * Les réservations PAYÉES ne sont pas comptées ici : elles aussi sont déduites de
     * placesDisponibles(), les afficher à part ferait doublon à l'écran.
     */
    public function placesReservees(Voyage $voyage, int $ordreMontee, int $ordreDescente, int $identreprise): int
    {
        $ordreParGare = $this->ordreParGare($voyage->getLigne());
        $count = 0;
        foreach ($this->reservationRepository->findEnAttentePourVoyage($voyage->getId(), $identreprise) as $resa) {
            $rm = $ordreParGare[$resa->getGare()?->getId()] ?? null;
            $rd = $ordreParGare[$resa->getGaredescente()?->getId()] ?? null;
            if ($rm !== null && $rd !== null && $rm < $ordreDescente && $rd > $ordreMontee) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifie qu'au moins UNE place est libre sur le tronçon, sinon lève une 400.
     */
    public function assertPlaceDisponible(
        Voyage $voyage,
        int $ordreMontee,
        int $ordreDescente,
        int $identreprise
    ): void
    {
        if ($this->capaciteEffective($voyage) === null) {
            throw new BadRequestHttpException('Aucune capacité définie pour ce voyage : affectez un car ou renseignez les places prévues.');
        }

        // Billets émis ET réservations qui tiennent une place, présents AU POINT DE MONTÉE : on ne
        // vend pas au-delà des sièges du car à cet instant. Ce qui part d'une gare en aval ne compte
        // pas — l'amont est prioritaire (cf. placesDisponibles).
        if ($this->placesDisponibles($voyage, $ordreMontee, $ordreDescente, $identreprise) <= 0) {
            throw new BadRequestHttpException('Plus de place disponible sur ce tronçon pour ce voyage.');
        }
    }

    /**
     * La place d'une réservation est-elle ENCORE disponible ? Garde partagée par les DEUX chemins de
     * paiement : l'encaissement au guichet (ConfirmerReservationProcessor) et le webhook du prestataire
     * (ReservationConfirmationService). Sans elle, on encaisse un client à qui on ne pourra jamais
     * émettre de billet.
     *
     * La réservation est EXCLUE de son propre calcul : elle tient déjà sa place (hold de paiement),
     * la compter la ferait échouer contre elle-même. Reste donc détecté le vrai cas problématique :
     * une capacité devenue insuffisante (car plus petit, ventes au guichet entre-temps).
     */
    public function placeEncoreDisponiblePour(Reservation $reservation): bool
    {
        $voyage = $reservation->getVoyage();
        $ligne = $voyage?->getLigne();
        if ($voyage === null || $ligne === null) {
            return true; // sans ligne, on ne sait pas juger : on ne bloque pas le paiement
        }

        $ordreParGare = $this->ordreParGare($ligne);
        $ordreMontee = $ordreParGare[$reservation->getGare()?->getId()] ?? null;
        $ordreDescente = $ordreParGare[$reservation->getGaredescente()?->getId()] ?? null;
        if ($ordreMontee === null || $ordreDescente === null || $ordreMontee >= $ordreDescente) {
            return true;
        }

        return $this->placesDisponibles(
            $voyage,
            $ordreMontee,
            $ordreDescente,
            (int) $reservation->getIdentreprise(),
            $reservation->getId()
        ) > 0;
    }

    /**
     * Nombre de SIÈGES qu'un véhicule doit au moins offrir pour honorer ce qui est déjà vendu ou
     * réservé : le maximum, sur un segment quelconque, des sièges simultanément immobilisés.
     *
     * Compte des sièges, pas des passagers — même décompte que {@see placesDisponibles}. Avec le
     * surbooking amont assumé, un segment peut porter plus de passagers que de sièges ; exiger un
     * véhicule assez grand pour tous refuserait alors jusqu'à la simple modification d'un voyage,
     * véhicule inchangé. Ce qu'un car doit pouvoir accueillir, ce sont les sièges occupés.
     */
    public function occupationMaximale(Voyage $voyage, int $identreprise): int
    {
        $intervalles = $this->intervallesOccupation($voyage, $identreprise, null);
        if ($intervalles === []) {
            return 0;
        }

        $debut = min(array_column($intervalles, 'debut'));
        $fin = max(array_column($intervalles, 'fin'));

        $max = 0;
        for ($k = $debut; $k < $fin; $k++) {
            $max = max($max, $this->occupationAu($k, $intervalles));
        }

        return $max;
    }

    /**
     * Billets ÉVINCÉS : ceux dont le siège est déjà occupé, à LEUR PROPRE point de montée, par un
     * passager monté plus tôt. Le surbooking amont étant assumé, ces passagers ne monteront pas.
     *
     * Ils ne doivent donc compter ni dans l'occupation, ni dans les montées et descentes annoncées au
     * chef de gare ou au chauffeur : le manifeste décrirait sinon un car que personne ne peut occuper.
     *
     * L'attribution se fait dans l'ORDRE DE MONTÉE, siège par siège : le plus amont garde la place,
     * et seul un occupant RETENU peut en évincer un autre. Un billet lui-même évincé ne prend aucune
     * place, il ne saurait donc en priver un troisième — c'est pourquoi on ne peut pas se contenter
     * de comparer les intervalles deux à deux.
     *
     * @return array<int, true> ids des billets évincés
     */
    public function billetsEvinces(Voyage $voyage, int $identreprise): array
    {
        $ordreParGare = $this->ordreParGare($voyage->getLigne());
        $ordreTerminus = $voyage->getLigne()
            ? ($ordreParGare[$voyage->getLigne()->getGareterminus()->getId()] ?? PHP_INT_MAX)
            : PHP_INT_MAX;

        $parSiege = [];
        foreach ($this->ticketRepository->findBy([
            'voyage' => $voyage->getId(),
            'identreprise' => $identreprise,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]) as $ticket) {
            $siegeId = $ticket->getSiege()?->getId();
            $mo = $ordreParGare[$ticket->getGare()?->getId()] ?? null;
            if ($siegeId === null || $mo === null) {
                continue; // sans siège, rien à disputer
            }
            $descenteEff = $ticket->getGaredescenteEffective();
            $do = $descenteEff ? ($ordreParGare[$descenteEff->getId()] ?? $ordreTerminus) : $ordreTerminus;
            if ($do > $mo) {
                $parSiege[$siegeId][] = ['id' => $ticket->getId(), 'debut' => $mo, 'fin' => $do];
            }
        }

        $evinces = [];
        foreach ($parSiege as $billets) {
            usort($billets, fn ($a, $b) => $a['debut'] <=> $b['debut']);
            $retenus = [];
            foreach ($billets as $billet) {
                foreach ($retenus as $occupant) {
                    if ($occupant['debut'] <= $billet['debut'] && $occupant['fin'] > $billet['debut']) {
                        $evinces[$billet['id']] = true;
                        continue 2;
                    }
                }
                $retenus[] = $billet;
            }
        }

        return $evinces;
    }

    /**
     * Occupation constatée AU POINT $ordre, comptée en SIÈGES et non en passagers.
     *
     * Un siège porté par plusieurs billets à cet instant ne compte qu'UNE fois : le surbooking amont
     * (assumé) met deux passagers sur un même siège, mais il n'immobilise qu'un siège. Compter les
     * passagers rendait la libération en route inopérante — une gare intermédiaire libérait un siège
     * réellement vide (le client est descendu chez elle) et ne pouvait pas le revendre, le décompte
     * restant saturé par des passagers qui, eux, se partagent un siège.
     *
     * Les réservations n'ont pas de siège attribué : chacune immobilise une place à part entière.
     *
     * @param array<int, array{debut:int, fin:int, siege:?int}> $entrees
     */
    private function occupationAu(int $ordre, array $entrees): int
    {
        $sieges = [];
        $sansSiege = 0;
        foreach ($entrees as $e) {
            if ($e['debut'] > $ordre || $e['fin'] <= $ordre) {
                continue;
            }
            if ($e['siege'] === null) {
                $sansSiege++;
                continue;
            }
            $sieges[$e['siege']] = true;
        }

        return count($sieges) + $sansSiege;
    }

    /**
     * Places TENUES sur le voyage : intervalle [montée, descente) et siège occupé s'il y en a un.
     * Deux sources :
     *  1. les billets VALIDE (émis) — le siège est physiquement occupé ;
     *  2. les réservations qui tiennent une place (payée, ou impayée pendant sa fenêtre de paiement),
     *     SANS siège attribué : elles immobilisent une place, pas un siège précis.
     *
     * @return array<int, array{debut:int, fin:int, siege:?int}>
     */
    private function intervallesOccupation(Voyage $voyage, int $identreprise, ?int $exclureReservationId): array
    {
        $ordreParGare = $this->ordreParGare($voyage->getLigne());
        $ordreTerminus = $voyage->getLigne()
            ? ($ordreParGare[$voyage->getLigne()->getGareterminus()->getId()] ?? PHP_INT_MAX)
            : PHP_INT_MAX;

        $intervalles = [];

        $tickets = $this->ticketRepository->findBy([
            'voyage' => $voyage->getId(),
            'identreprise' => $identreprise,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]);
        foreach ($tickets as $ticket) {
            $mo = $ordreParGare[$ticket->getGare()?->getId()] ?? null;
            $descenteEff = $ticket->getGaredescenteEffective();
            $do = $descenteEff ? ($ordreParGare[$descenteEff->getId()] ?? $ordreTerminus) : $ordreTerminus;
            if ($mo !== null && $do > $mo) {
                $intervalles[] = ['debut' => $mo, 'fin' => $do, 'siege' => $ticket->getSiege()?->getId()];
            }
        }

        foreach ($this->reservationRepository->findTenantPlacePourVoyage($voyage->getId(), $identreprise, $exclureReservationId) as $resa) {
            $mo = $ordreParGare[$resa->getGare()?->getId()] ?? null;
            $do = $ordreParGare[$resa->getGaredescente()?->getId()] ?? $ordreTerminus;
            if ($mo !== null && $do > $mo) {
                $intervalles[] = ['debut' => $mo, 'fin' => $do, 'siege' => null];
            }
        }

        return $intervalles;
    }

    /** @return array<int, int> map gareId => ordre */
    private function ordreParGare(?Ligne $ligne): array
    {
        $map = [];
        if ($ligne) {
            foreach ($ligne->getArrets() as $arret) {
                $map[$arret->getGare()->getId()] = $arret->getOrdre();
            }
        }

        return $map;
    }
}
