<?php

namespace App\Entity\Dto;

use App\Entity\Gare;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entrée de l'action « le passager descend ici » (/tickets/{id}/descendre) :
 * la gare où le passager quitte réellement le car, en amont de sa descente vendue,
 * pour libérer le siège en aval et le revendre.
 */
class DescendreInput
{
    #[Groups(['write:DescendreInput'])]
    #[Assert\NotNull(message: 'La gare de descente réelle est obligatoire')]
    public ?Gare $gare = null;
}
