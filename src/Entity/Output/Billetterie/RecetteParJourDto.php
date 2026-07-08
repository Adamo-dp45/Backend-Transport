<?php

namespace App\Entity\Output\Billetterie;

final class RecetteParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $montant,
        public readonly int $nbtickets
    )
    {
    }
}