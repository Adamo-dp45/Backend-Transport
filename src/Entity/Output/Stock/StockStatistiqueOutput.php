<?php

namespace App\Entity\Output\Stock;

final class StockStatistiqueOutput
{
    public function __construct(
        public readonly int $totalPieces,
        public readonly int $piecesCritiques,
        /** @var StockPieceDto[] */
        public readonly array $stockParPiece,
        /** @var MouvementRecentDto[] */
        public readonly array $mouvementsRecents
    )
    {
    }
}