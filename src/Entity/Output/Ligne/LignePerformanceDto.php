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
        public readonly float $recette, // grand total (billets + courriers + bagages)
        /*
            LES CHARGES RATTACHÉES AUX DÉPARTS DE LA LIGNE, et le résultat qui en découle.

            'null' et non zéro quand l'acteur n'a pas le droit de lire des montants de charge :
            « je ne sais pas » ne s'écrit pas comme « il n'y en a pas ». Même prudence que
            'VoyageResultatService::pour($voyage, $avecDepenses)'.

            !! CE N'EST PAS UN BÉNÉFICE, et l'écran ne doit pas l'appeler ainsi. Seules y entrent les
            charges rattachées à un DÉPART (frais de route, carburant) : les salaires, le loyer, les
            dépannages et les achats de pièces n'ont aucun lien avec une ligne et ne peuvent pas y
            être répartis sans clé inventée. On écrit « résultat », comme pour une gare ou un départ.
        */
        public readonly ?float $depenses = null,
        public readonly ?float $resultat = null
    )
    {
    }
}
