<?php

namespace App\Domain\Enum;

/**
 * Niveau d'urgence d'une alerte — pilote le TRI (cloche, listing) et la couleur côté FT.
 */
enum AlerteSeverite: string
{
    case CRITIQUE      = 'CRITIQUE';      // action immédiate (rupture de stock, départ en retard)
    case AVERTISSEMENT = 'AVERTISSEMENT'; // à traiter (stock faible, évincés, dépannage qui traîne)
    case INFO          = 'INFO';          // pour information (no-show à régulariser, colis non livré)

    /** Poids de tri décroissant : le plus urgent d'abord. */
    public function poids(): int
    {
        return match ($this) {
            self::CRITIQUE      => 3,
            self::AVERTISSEMENT => 2,
            self::INFO          => 1,
        };
    }
}
