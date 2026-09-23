<?php

namespace App\Entity\Output\Depense;

/**
 * Ce qu'une gare a encaissé et dépensé, et ce qui lui reste.
 *
 * 'resultat' n'est PAS un bénéfice : les dépannages et les approvisionnements ne sont rattachés à
 * aucune gare et ne peuvent donc pas y être déduits (cf. 'DepenseGareService'). Le champ 'perimetre'
 * de la sortie le dit à l'écran.
 */
final class DepenseParGareDto
{
    public function __construct(
        public readonly ?int $gareId, // null = le SIÈGE
        public readonly string $libelle,
        public readonly int $depenses,
        public readonly int $nb,
        public readonly int $recette,
        public readonly int $resultat
    )
    {
    }
}
