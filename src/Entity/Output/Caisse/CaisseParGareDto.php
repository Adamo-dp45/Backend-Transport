<?php

namespace App\Entity\Output\Caisse;

final class CaisseParGareDto
{
    public function __construct(
        public readonly int $gareId,
        public readonly string $gareLibelle,
        public readonly int $nbtickets,
        public readonly float $recetteTickets,
        public readonly int $nbcourriers,
        public readonly float $recetteCourriers,
        public readonly int $nbbagages,
        public readonly float $recetteBagages,
        public readonly float $recetteTotale
    )
    {
    }
}
