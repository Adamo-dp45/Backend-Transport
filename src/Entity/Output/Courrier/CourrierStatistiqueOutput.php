<?php

namespace App\Entity\Output\Courrier;

final class CourrierStatistiqueOutput
{
    public function __construct(
        public readonly int $totalCourriers,
        public readonly int $enAttente,
        public readonly int $enTransit,
        public readonly int $receptionnes,
        public readonly int $livres,
        public readonly int $perdus,
        public readonly int $annules,
        public readonly float $recetteTotale,
        /** @var RecetteCourrierParJourDto[] */
        public readonly array $recettesParJour,
        /** @var RecetteCourrierParTrajetDto[] */
        public readonly array $recettesParTrajet
    )
    {
    }
}