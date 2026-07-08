<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ApprovisionnementInput
{
    #[Assert\NotNull]
    #[Groups(['write:ApprovisionnementInput'])]
    public int $fournisseur;

    // #[Assert\Valid]
    #[Assert\Count(min: 1)]
    #[Groups(['write:ApprovisionnementInput'])]
    public array $details = []; /* Ou..
        /** @var DetailApprovisionnementInput[] * public array $details = [] -- Va permettre de valider chaque ligne individuellement
    */
}