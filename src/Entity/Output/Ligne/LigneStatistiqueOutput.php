<?php

namespace App\Entity\Output\Ligne;

final class LigneStatistiqueOutput
{
    public function __construct(
        public readonly int   $totalLignes,
        /** @var LignePerformanceDto[] */
        public readonly array $performances,
    )
    {
    }
}
