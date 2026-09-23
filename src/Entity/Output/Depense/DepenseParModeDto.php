<?php

namespace App\Entity\Output\Depense;

/**
 * Ventilation par mode de règlement. Les ESPÈCES seront la base du rapprochement de caisse : ce
 * sont les seules dépenses qui sortent physiquement du tiroir d'une gare.
 */
final class DepenseParModeDto
{
    public function __construct(
        public readonly string $mode,
        public readonly int $montant,
        public readonly int $nb
    )
    {
    }
}
