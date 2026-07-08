<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class AffectpersonnelInput
{
    #[Assert\NotNull]
    #[Groups(['write:AffectpersonnelInput'])]
    public int $personnel;

    #[Assert\NotBlank]
    #[Groups(['write:AffectpersonnelInput'])]
    public string $motif;
}