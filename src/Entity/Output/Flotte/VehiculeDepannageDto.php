<?php

namespace App\Entity\Output\Flotte;

final class VehiculeDepannageDto
{
    public function __construct(
        public readonly string $matricule,
        public readonly int $nbrdepannages
    )
    {
    }
}