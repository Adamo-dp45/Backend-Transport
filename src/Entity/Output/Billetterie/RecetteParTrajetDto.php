<?php

namespace App\Entity\Output\Billetterie;

final class RecetteParTrajetDto
{
    public function __construct(
        public readonly string $trajet,
        public readonly float $montant,
        public readonly int $nbtickets
    )
    {
    }
}