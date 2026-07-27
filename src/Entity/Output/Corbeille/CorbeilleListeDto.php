<?php

namespace App\Entity\Output\Corbeille;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Vue d'ensemble de la corbeille : compteurs EXACTS (pour les badges/filtres) + éléments (limités
 * par type pour tenir le volume). Les compteurs ne dépendent pas de la limite d'affichage.
 */
final class CorbeilleListeDto
{
    /**
     * @param array<int, array{type: string, typeLibelle: string, count: int}>  $parType
     * @param array<int, array{id: int, libelle: string, count: int}>           $parEntreprise
     * @param CorbeilleItemDto[]                                                 $items
     */
    public function __construct(
        #[Groups(['read:Corbeille'])]
        public readonly int $total,
        #[Groups(['read:Corbeille'])]
        public readonly array $parType,
        #[Groups(['read:Corbeille'])]
        public readonly array $parEntreprise,
        #[Groups(['read:Corbeille'])]
        public readonly array $items,
    ) {
    }
}
