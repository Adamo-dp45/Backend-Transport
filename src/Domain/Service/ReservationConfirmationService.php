<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Confirme le PAIEMENT EN LIGNE d'une réservation à partir de la référence prestataire — appelé par
 * le webhook du prestataire (réel) OU, en simulation, par le front. Idempotent et défensif : ne
 * confirme que si la réservation est EN_ATTENTE, non expirée et sur un voyage non clôturé.
 *
 * N'attribue PAS de siège / n'émet PAS de billet (le bon payé reste distinct du billet de gare,
 * cf. EmettreBilletReservationProcessor). La place était déjà tenue depuis la création.
 */
class ReservationConfirmationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ReservationRepository $reservationRepository,
        private ReservationEcheanceService $echeance,
        private CapaciteService $capaciteService,
        private VoyageGuard $voyageGuard
    )
    {
    }

    /** @return Reservation|null la réservation confirmée, ou null si la référence est inconnue */
    public function confirmerParReference(string $reference): ?Reservation
    {
        $reservation = $this->reservationRepository->findOneBy([
            'referencepaiement' => $reference,
            'deletedAt' => null,
        ]);
        if ($reservation === null) {
            return null;
        }

        // Idempotent : déjà payée → on renvoie tel quel (le prestataire peut renvoyer le webhook 2×)
        if ($reservation->getEtatpaiement() === 'PAYE') {
            return $reservation;
        }
        // Défensif : on ne confirme que ce qui est encore confirmable
        if ($reservation->getStatut() !== ReservationStatus::STATUT_EN_ATTENTE->value) {
            return $reservation;
        }
        if ($reservation->getDateexpiration() !== null && $reservation->getDateexpiration() <= new \DateTimeImmutable()) {
            return $reservation; // bon expiré : ne pas confirmer — politique maison : AUCUN remboursement
        }
        if ($reservation->getVoyage()?->getDatearriveereelle() !== null) {
            return $reservation; // voyage clôturé
        }
        /*
            Car déjà reparti de la gare de montée : on ne confirme pas un paiement pour une place qui
            ne pourra pas être occupée. Le passage du car clôt normalement l'échéance (contrôle
            ci-dessus), ce garde-fou tient même si ce recalcul n'a pas eu lieu.
        */
        if ($this->voyageGuard->monteeDepassee($reservation->getVoyage(), $reservation->getGare())) {
            return $reservation;
        }

        /*
            CAPACITÉ au moment du paiement. Une réservation EN ATTENTE ne tient aucune place : entre sa
            création et son paiement, le guichet (ou d'autres réservations payées) a pu remplir le
            tronçon (car plus petit, ventes au guichet). Confirmer reviendrait à encaisser un client à
            qui on ne pourra JAMAIS émettre de billet. Cas devenu rare depuis que la réservation tient
            sa place le temps du paiement — mais pas impossible, d'où cette garde.
        */
        if (!$this->capaciteService->placeEncoreDisponiblePour($reservation)) {
            return $reservation;
        }

        $reservation
            ->setEtatpaiement('PAYE')
            ->setDatepaiement(new \DateTimeImmutable())
            ->setStatut(ReservationStatus::STATUT_CONFIRMEE->value);

        /*
            L'échéance change de NATURE au paiement : elle portait le délai de PAIEMENT (court, compté
            depuis la création), elle porte désormais le délai de PRÉSENTATION au guichet. Sans cela,
            un client ayant payé aurait été déclaré no-show à l'heure limite de paiement — alors qu'il
            lui reste tout le temps jusqu'au passage du car pour retirer son billet.
            À partir d'ici la place est TENUE (statut CONFIRMEE, cf. CapaciteService).
        */
        $presentation = $this->echeance->limitePresentationPour(
            $reservation->getVoyage(),
            $reservation->getGare(),
            (int) $reservation->getIdentreprise()
        );
        if ($presentation !== null) {
            $reservation->setDateexpiration($presentation);
        }

        $this->em->flush();

        return $reservation;
    }

}
