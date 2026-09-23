<?php

namespace App\Entity\Output\Financier;

/**
 * Les trois postes de coût d'une journée. Ils ne se recouvrent JAMAIS : les dépannages et les
 * approvisionnements viennent de leurs modules, les dépenses sont saisies à la main et ne sont
 * jamais dérivées des deux autres (cf. l'en-tête de 'Depense').
 */
final class CoutParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $depannage,
        public readonly float $approvisionnement,
        // Valeur par défaut : le poste est arrivé après les deux autres, et l'unique site de
        // construction passe ses arguments par nom.
        public readonly float $depense = 0.0
    )
    {
    }
}