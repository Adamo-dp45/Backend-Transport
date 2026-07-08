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
     * Arrêts ordonnés : [['gare' => 12, 'ordre' => 0], ['gare' => 7, 'ordre' => 1], ...]
     * @var array<int, array{gare: int, ordre: int}>
     */
    #[Assert\Count(min: 2, minMessage: 'Une ligne doit avoir au moins 2 arrêts (origine et terminus)')]
    #[Groups(['write:LigneInput'])]
    public array $arrets = [];
}
