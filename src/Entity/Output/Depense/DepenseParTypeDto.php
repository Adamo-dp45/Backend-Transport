<?php

namespace App\Entity\Output\Depense;

/** Un poste de dépense sur la période — l'axe « où part l'argent ». */
final class DepenseParTypeDto
{
    public function __construct(
        public readonly string $libelle,
        public readonly int $montant,
        public readonly int $nb,
        /** Part du total des dépenses, en % — calculée ici pour que l'écran n'ait rien à refaire. */
        public readonly float $part
    )
    {
    }
}
