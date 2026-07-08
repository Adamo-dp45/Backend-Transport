<?php

namespace App\Entity\Output\Stock;

final class StockPieceDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly int $stockactuel,
        public readonly int $seuilstock,
        public readonly bool $critique
    )
    {
    }
}