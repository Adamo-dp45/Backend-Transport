<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class DepannageInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:DepannageInput'])]
    public string $lieudepannage;

    #[Groups(['write:DepannageInput'])]
    public ?string $description = null;

    #[Assert\NotNull]
    #[Groups(['write:DepannageInput'])]
    public int $car;

    #[Assert\NotNull]
    #[Groups(['write:DepannageInput'])]
    public int $typepanne;

    // #[Assert\Valid]
    #[Assert\Count(min: 1)]
    #[Groups(['write:DepannageInput'])]
    public array $details; /*
        - Va correspondre à 'Detaildepannage'
    */

    // 'Detailpersonnel' géré dans l'affectation
}