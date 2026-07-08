<?php

namespace App\Entity\Dto;

use App\Entity\User;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Affectation (ou retrait) du commercial d'un voyage : /voyages/{id}/commercial.
 * 'commercial' = null → on désaffecte le commercial.
 */
class AffectCommercialInput
{
    #[Groups(['write:AffectCommercialInput'])]
    public ?User $commercial = null;
}
