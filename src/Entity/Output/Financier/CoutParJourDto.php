<?php

namespace App\Entity\Output\Financier;

final class CoutParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $depannage,
        public readonly float $approvisionnement
    )
    {
    }
}