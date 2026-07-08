<?php

namespace App\Entity\Output\ReservationStats;

final class TopTrajetReservationDto
{
    public function __construct(
        public readonly string $montee,
        public readonly string $descente,
        public readonly int $total
    )
    {
    }
}
