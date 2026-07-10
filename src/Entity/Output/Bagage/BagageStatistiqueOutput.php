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
        public readonly array $recettesParJour,
        /**
         * Bagages à montant forcé par agent (anti sous-déclaration).
         * @var array<int, array{nom:string, nb:int, manque:int, nbsoustarif:int, nbhorsgrille:int}>
         */
        public readonly array $forcages = []
    )
    {
    }
}