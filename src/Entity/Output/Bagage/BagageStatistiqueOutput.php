<?php

namespace App\Entity\Output\Bagage;

final class BagageStatistiqueOutput
{
    public function __construct(
        public readonly int $totalBagages,
        public readonly int $enregistres,
        public readonly int $embarques,
        public readonly int $livres,
        public readonly int $perdus,
        public readonly int $annules,
        public readonly float $recetteTotale,
        public readonly int $poidsTotal,
        /** @var RecetteBagageParJourDto[] */
        public readonly array $recettesParJour
    )
    {
    }
}