<?php

namespace App\Entity\Output\Ligne;

/**
 * Performance d'une ligne = toute la recette générée sur ses voyages : billets directs (VALIDE, hors
 * désistés) + réservations payées + courriers + bagages. `recette` est le grand total ; les sous-totaux
 * et compteurs par type sont exposés pour le détail.
 */
final class LignePerformanceDto
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $libelle,
        public readonly string $codeligne,
        public readonly int $nbvoyages,
        public readonly int $nbtickets, // billets directs + réservations payées
        public readonly float $recetteBillets, // billets directs + réservations payées
        public readonly int $nbcourriers,
        public readonly float $recetteCourriers,
        public readonly int $nbbagages,
        public readonly float $recetteBagages,
        public readonly float $recette // grand total (billets + courriers + bagages)
    )
    {
    }
}
