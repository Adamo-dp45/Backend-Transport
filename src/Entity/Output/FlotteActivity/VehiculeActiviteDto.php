<?php

namespace App\Entity\Output\FlotteActivity;

final class VehiculeActiviteDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $matricule,
        public readonly string $etat,
        public readonly int $nbvoyages,
        public readonly int $nbdepannages,
        public readonly bool $actif
    )
    {
    }
}