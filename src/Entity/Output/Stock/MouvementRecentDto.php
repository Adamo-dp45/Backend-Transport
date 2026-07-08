<?php

namespace App\Entity\Output\Stock;

final class MouvementRecentDto
{
    public function __construct(
        public readonly string $piece,
        public readonly string $typemouvement,
        public readonly int $quantite,
        public readonly string $date
    )
    {
    }
}