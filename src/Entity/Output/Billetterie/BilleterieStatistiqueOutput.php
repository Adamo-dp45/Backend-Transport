<?php

namespace App\Entity\Output\Billetterie;

final class BilleterieStatistiqueOutput
{
    public function __construct(
        public readonly int $totalTickets,
        public readonly float $recetteTotale,
        /** @var RecetteParJourDto[] */
        public readonly array $recettesParJour,
        /** @var RecetteParTrajetDto[] */
        public readonly array $recettesParTrajet,
        /** @var RecetteParCarDto[] */
        public readonly array $recettesParCar,
        // Ventilation de la recette totale par CANAL de vente (guichet gare / à bord commercial /
        // réservation compte admin). Les trois totalisent recetteTotale.
        public readonly float $recetteGuichet = 0,
        public readonly float $recetteCommercial = 0,
        public readonly float $recetteReservation = 0
    )
    {
    }
}