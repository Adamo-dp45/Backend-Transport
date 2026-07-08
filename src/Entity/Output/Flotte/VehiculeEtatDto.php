<?php

namespace App\Entity\Output\Flotte;

final class VehiculeEtatDto
{
    public function __construct(
        public readonly string $etat,
        public readonly int $total
    )
    {
    }
}