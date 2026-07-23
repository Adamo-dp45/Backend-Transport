<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class LigneInput
{
    #[Assert\NotBlank]
    #[Groups(['write:LigneInput'])]
    public ?string $libelle = null; // ex: "Abidjan → Korhogo"

    #[Groups(['write:LigneInput'])]
    public ?string $heuredepart = null; // "08:00"

    /**
     * Arrêts ordonnés. 'dureeTronconMinutes' est le temps de trajet du TRONÇON qui MÈNE à cet arrêt
     * (depuis l'arrêt précédent) : null/0 à l'origine, puis les minutes de chaque segment. La somme
     * jusqu'à un arrêt donne son heure de passage, dont dépend l'échéance de présentation. Facultatif,
     * mais TOUT-OU-RIEN : soit tous les tronçons (hors origine) sont renseignés, soit aucun.
     *
     * [['gare' => 12, 'ordre' => 0, 'dureeTronconMinutes' => null],
     *  ['gare' => 7,  'ordre' => 1, 'dureeTronconMinutes' => 240],   // Abidjan → Bouaké
     *  ['gare' => 3,  'ordre' => 2, 'dureeTronconMinutes' => 180]]   // Bouaké → Korhogo
     *
     * @var array<int, array{gare: int, ordre: int, dureeTronconMinutes?: int|null}>
     */
    #[Assert\Count(min: 2, minMessage: 'Une ligne doit avoir au moins 2 arrêts (origine et terminus)')]
    #[Groups(['write:LigneInput'])]
    public array $arrets = [];
}
