<?php

namespace App\Entity\Output\Flotte;

final class FlotteStatistiqueOutput
{
    public function __construct(
        public readonly int   $totalVehicules,
        /** @var VehiculeEtatDto[] */
        public readonly array $vehiculesParEtat,
        /** @var VehiculeDepannageDto[] */
        public readonly array $vehiculesParDepannage,
        /** @var VehiculeMaintenanceDto[] */
        public readonly array $coutMaintenanceParVehicule,
    )
    {
    }
}