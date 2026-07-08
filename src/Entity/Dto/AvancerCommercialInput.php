<?php

namespace App\Entity\Dto;

use App\Entity\Gare;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le commercial à bord déclare la progression du car : /voyages/{id}/avancer.
 * 'gare' = la gare (arrêt de la ligne) où le car se trouve désormais (en aval de la position courante).
 */
class AvancerCommercialInput
{
    #[Groups(['write:AvancerCommercialInput'])]
    #[Assert\NotNull(message: 'La gare où se trouve le car est obligatoire')]
    public ?Gare $gare = null;
}
