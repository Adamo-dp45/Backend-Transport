<?php

namespace App\Entity\Output\Bagage;

final class RecetteBagageParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $montant,
        public readonly int $nbbagages,
        public readonly int $poids
    )
    {
    }
}