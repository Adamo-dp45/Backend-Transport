<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class BagageInput
{
    // Billet du client — OBLIGATOIRE. Le bagage SUIT le billet : voyage, gares (provenance/destination),
    // identité du client et CANAL de vente (guichet/commercial) en sont dérivés. Le DTO ne porte que le
    // billet + les attributs physiques propres au bagage (nature, type, poids, montant).
    #[Assert\NotNull(message: 'Le billet du client est obligatoire')]
    #[Groups(['write:BagageInput'])]
    public ?int $ticket = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:BagageInput'])]
    public string $nature;

    #[Assert\Choice(choices: ['LEGER', 'LOURD', 'VOLUMINEUX', 'FRAGILE'])]
    #[Groups(['write:BagageInput'])]
    public string $type;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['write:BagageInput'])]
    public int $poids;

    #[Assert\PositiveOrZero]
    #[Groups(['write:BagageInput'])]
    public ?int $montant = null; /*
        - Si fourni → montant forcé par l'agent
    */
}
