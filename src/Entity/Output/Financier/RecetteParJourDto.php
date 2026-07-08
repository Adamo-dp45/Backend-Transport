<?php

namespace App\Entity\Output\Financier;

final class RecetteParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $montant
    )
    {
    }
}