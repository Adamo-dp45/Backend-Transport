<?php

namespace App\Entity\Output\Caisse;

final class CaisseDetailVoyageDto
{
    public function __construct(
        public readonly string $codevoyage,
        public readonly string $provenance,
        public readonly string $destination,
        public readonly int    $nbtickets,
        public readonly float  $recette,
    )
    {
    }
}