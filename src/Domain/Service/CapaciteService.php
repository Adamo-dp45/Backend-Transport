<?php

namespace App\Domain\Service;

use App\Domain\Enum\TicketStatus;
use App\Entity\Ligne;
use App\Entity\Voyage;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Capacité en PLACES d'un voyage par tronçon, partagée entre la vente de billets et la réservation.
 *
 * Une place n'est « tenue » que par un billet VALIDE (émis). Une réservation NON émise ne consomme PAS
 * de capacité : elle reste INDICATIVE (surréservation tolérée) et n'est comptée que par placesReservees()
 * pour informer l'agent. La capacité effective est celle du car affecté (nb de sièges) ou, à défaut, la
 * capacité prévisionnelle du voyage ('placesprevues') tant qu'aucun car n'est affecté.
 *
 * Modèle par segment élémentaire de la ligne : sur chaque segment, le nombre de billets VALIDE qui le
 * recouvrent doit rester < capacité. Cohérent avec la priorité « par tronçon » de la billetterie.
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
     * Places encore disponibles sur le tronçon [ordreMontee, ordreDescente) du voyage.
     * Renvoie capacité − occupation maximale sur les segments du tronçon.
     */
    public function placesDisponibles(
        Voyage $voyage,
        int $ordreMontee,
        int $ordreDescente,
        int $identreprise
    ): int
    {
        $capacite = $this->capaciteEffective($voyage);
        if ($capacite === null) {
            return 0;
        }

        $ordreParGare = $this->ordreParGare($voyage->getLigne());
        $ordreTerminus = $voyage->getLigne()
            ? ($ordreParGare[$voyage->getLigne()->getGareterminus()->getId()] ?? PHP_INT_MAX)
            : PHP_INT_MAX;

        // Intervalles [montée, descente) des places PHYSIQUEMENT tenues = uniquement les billets VALIDE
        // (billets ÉMIS). Les réservations NON encore émises ne bloquent PAS la capacité : le client
        // peut ne pas se présenter → elles restent seulement INDICATIVES (voir placesReservees()).
        // Une place n'est réellement consommée qu'à l'émission du billet (qui crée un ticket VALIDE).
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
                $intervalles[] = [$mo, $do];
            }
        }

        // Occupation maximale sur les segments [ordreMontee, ordreDescente)
        $maxOcc = 0;
        for ($k = $ordreMontee; $k < $ordreDescente; $k++) {
            $occ = 0;
            foreach ($intervalles as [$mo, $do]) {
                if ($mo <= $k && $k < $do) {
                    $occ++;
                }
            }
            $maxOcc = max($maxOcc, $occ);
        }

        return $capacite - $maxOcc;
    }

    /**
     * Nombre de PLACES RÉSERVÉES (réservations actives) qui recouvrent le tronçon [montée, descente).
     * Sert à informer l'agent au guichet (« X places réservées sur ce tronçon »).
     */
    public function placesReservees(Voyage $voyage, int $ordreMontee, int $ordreDescente, int $identreprise): int
    {
        $ordreParGare = $this->ordreParGare($voyage->getLigne());
        $count = 0;
        foreach ($this->reservationRepository->findActivesPourVoyage($voyage->getId(), $identreprise) as $resa) {
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

        // Seuls les billets ÉMIS bloquent : une réservation non émise ne consomme pas de place ici.
        if ($this->placesDisponibles($voyage, $ordreMontee, $ordreDescente, $identreprise) <= 0) {
            throw new BadRequestHttpException('Plus de place disponible sur ce tronçon pour ce voyage.');
        }
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
