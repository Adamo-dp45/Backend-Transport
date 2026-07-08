<?php

namespace App\Entity\Output\Personnel;

final class PersonnelStatistiqueOutput
{
    public function __construct(
        public readonly int   $totalPersonnels,
        public readonly int   $personnelsActifs,
        /** @var PersonnelPerformanceDto[] */
        public readonly array $performances,
    )
    {
    }
}