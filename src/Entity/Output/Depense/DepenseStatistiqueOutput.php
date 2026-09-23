<?php

namespace App\Entity\Output\Depense;

/**
 * L'écran d'analyse des charges : où part l'argent, dans quelle gare, à quel rythme, par quel moyen.
 *
 * RÉCONCILIATION — les deux totaux du bas existent pour qu'aucun chiffre ne mente par omission :
 * 'total' est la somme de toutes les dépenses SAISIES (gares + siège), et c'est exactement le poste
 * soustrait au bénéfice ; 'nonImputeAUneGare' rappelle à côté ce que la compagnie dépense AUSSI
 * mais qu'aucune gare ne porte (dépannages et achats de pièces). Deux totaux exacts valent mieux
 * qu'un chiffre unique qui prétendrait tout couvrir.
 */
final class DepenseStatistiqueOutput
{
    public function __construct(
        public readonly int $total, // dépenses saisies : gares + siège
        public readonly int $nb,
        public readonly int $totalGares,
        public readonly int $totalSiege,
        /** Dépannages + approvisionnements : des charges réelles, rattachées à aucune gare. */
        public readonly int $nonImputeAUneGare,
        public readonly int $coutDepannages,
        public readonly int $coutApprovisionnements,
        /** Ce que le résultat par gare ne déduit pas — à afficher, pas à taire. */
        public readonly string $perimetreResultat,
        /** @var DepenseParTypeDto[] */
        public readonly array $parType,
        /** @var DepenseParGareDto[] */
        public readonly array $parGare,
        /** @var DepenseParPeriodeDto[] */
        public readonly array $parMois,
        /** @var DepenseParModeDto[] */
        public readonly array $parMode,
    )
    {
    }
}
