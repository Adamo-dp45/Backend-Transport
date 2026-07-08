<?php

namespace App\Entity\Output\Financier;

final class FinancierStatistiqueOutput
{
    public function __construct(
        public readonly float $recettesTotales, // Les tickets + courriers + bagages
        public readonly float $recettesTickets,
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