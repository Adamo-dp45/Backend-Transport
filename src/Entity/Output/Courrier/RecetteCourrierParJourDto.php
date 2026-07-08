<?php

namespace App\Entity\Output\Courrier;

final class RecetteCourrierParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $montant,
        public readonly int $nbcourriers
    )
    {
    }
}