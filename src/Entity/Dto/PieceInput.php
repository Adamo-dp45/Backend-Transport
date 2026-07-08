<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class PieceInput
{
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2)]
    #[Groups(['write:PieceInput'])]
    public string $libelle;

    #[Assert\NotBlank()]
    #[Groups(['write:PieceInput'])]
    public int $stockInitial;

    #[Groups(['write:PieceInput'])]
    public ?int $typepieceId = null;

    #[Groups(['write:PieceInput'])]
    public ?int $marquepieceId = null;

    #[Groups(['write:PieceInput'])]
    public ?int $modelepieceId = null;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['write:PieceInput'])]
    public int $prixunitaire;

    // public ?string $image = null;
}