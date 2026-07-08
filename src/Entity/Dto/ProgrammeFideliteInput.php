<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ProgrammeFideliteInput
{
    #[Groups(['write:ProgrammeFideliteInput'])]
    #[Assert\NotNull]
    #[Assert\Positive(message: 'Le seuil doit être supérieur à 0')]
    public ?int $seuil = null;

    #[Groups(['write:ProgrammeFideliteInput'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'La remise doit être comprise entre 1 et 100 %')]
    public ?int $recompensePourcentage = null;

    #[Groups(['write:ProgrammeFideliteInput'])]
    #[Assert\NotNull]
    public ?bool $actif = null;
}
