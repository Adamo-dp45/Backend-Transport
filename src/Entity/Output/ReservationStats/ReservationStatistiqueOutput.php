<?php

namespace App\Entity\Output\ReservationStats;

/** Statistiques du module RÉSERVATION sur une période (KPIs + répartitions). */
final class ReservationStatistiqueOutput
{
    public function __construct(
        public readonly int $total,
        public readonly int $confirmees,
        public readonly int $enAttente,
        public readonly int $aRegulariser,        // payées no-show, récupérables (report + pénalité)
        public readonly int $expirees,
        public readonly int $annulees,
        public readonly int $billetsEmis,
        public readonly int $noShows,
        public readonly float $tauxConversion,   // % payées (confirmées + à régulariser) / total
        public readonly int $recetteConfirmee,    // FCFA (réservations payées)
        public readonly float $partMobile,        // % des réservations créées via le canal MOBILE
        /** @var ReservationParStatutDto[] */
        public readonly array $parStatut,
        /** @var ReservationParSourceDto[] */
        public readonly array $parSource,
        /** @var TopTrajetReservationDto[] */
        public readonly array $topTrajets
    )
    {
    }
}
