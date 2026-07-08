<?php

namespace App\Entity\Output\Caisse;

final class CaisseParJourDto
{
    public function __construct(
        public readonly string $jour,
        public readonly int $nbtickets,
        public readonly float $recetteTickets,
        public readonly int $nbcourriers,
        public readonly float  $recetteCourriers,
        public readonly int $nbbagages,
        public readonly float $recetteBagages,
        public readonly float $recetteTotale,
        /** @var CaisseDetailVoyageDto[] */
        public readonly array  $detailParVoyage
    )
    {
    }
}