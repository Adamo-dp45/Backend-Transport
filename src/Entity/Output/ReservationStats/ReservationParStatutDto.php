<?php

namespace App\Entity\Output\ReservationStats;

final class ReservationParStatutDto
{
    public function __construct(
        public readonly string $statut,
        public readonly int $total,
        public readonly int $recette
    )
    {
    }
}
