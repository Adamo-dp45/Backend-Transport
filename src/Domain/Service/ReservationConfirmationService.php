<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
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
        private ReservationRepository $reservationRepository
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
            return $reservation; // bon expiré : ne pas confirmer (paiement tardif à rembourser côté presta)
        }
        if ($reservation->getVoyage()?->getDatearriveereelle() !== null) {
            return $reservation; // voyage clôturé
        }

        $reservation
            ->setEtatpaiement('PAYE')
            ->setDatepaiement(new \DateTimeImmutable())
            ->setStatut(ReservationStatus::STATUT_CONFIRMEE->value);

        $this->em->flush();

        return $reservation;
    }
}
