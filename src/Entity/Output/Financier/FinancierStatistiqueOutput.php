<?php

namespace App\Entity\Output\Financier;

final class FinancierStatistiqueOutput
{
    public function __construct(
        public readonly float $recettesTotales, // tickets (hors résa) + réservations payées + courriers + bagages
        public readonly float $recettesTickets, // billets vendus directs (guichet + commercial), HORS réservation
        public readonly float $recettesReservations, // réservations payées (recette reconnue au paiement)
        public readonly float $recettesCourriers,
        public readonly float $recettesBagages,
        public readonly float $coutDepannages,
        public readonly float $coutApprovisionnements,
        public readonly float $beneficeNet,
        /** @var RecetteParJourDto[] */
        public readonly array $recettesParJour,
        /** @var CoutParJourDto[] */
        public readonly array $coutsParJour,
    )
    {
    }
}