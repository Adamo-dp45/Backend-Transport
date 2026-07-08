<?php

namespace App\Entity\Output\FlotteActivity;

final class FlotteActiviteOutput
{
    public function __construct(
        public readonly int $totalVehicules,
        public readonly int $vehiculesEnVoyage,
        /** @var VehiculeActiviteDto[] */
        public readonly array $activiteParVehicule
    )
    {
    }
}