<?php

namespace App\Entity\Output\Ligne;

final class LignePerformanceDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $libelle,
        public readonly string $codeligne,
        public readonly int $nbvoyages,
        public readonly int $nbtickets,
        public readonly float $recette
    )
    {
    }
}
