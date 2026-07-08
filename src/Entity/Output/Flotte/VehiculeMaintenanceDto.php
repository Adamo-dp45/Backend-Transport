<?php

namespace App\Entity\Output\Flotte;

final class VehiculeMaintenanceDto
{
    public function __construct(
        public readonly string $matricule,
        public readonly float  $couttotal
    )
    {
    }
}