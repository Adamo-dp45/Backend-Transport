<?php

namespace App\Entity\Output\Billetterie;

final class RecetteParCarDto
{
    public function __construct(
        public readonly string $matricule,
        public readonly float $montant,
        public readonly int $nbtickets
    )
    {
    }
}