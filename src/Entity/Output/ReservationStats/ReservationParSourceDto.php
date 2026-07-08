<?php

namespace App\Entity\Output\ReservationStats;

final class ReservationParSourceDto
{
    public function __construct(
        public readonly string $source,
        public readonly int $total
    )
    {
    }
}
