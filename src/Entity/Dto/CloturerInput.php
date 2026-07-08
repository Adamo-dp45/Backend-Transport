<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

class CloturerInput
{
    // Heure d'arrivée réelle (= clôture). Optionnelle : par défaut « maintenant » côté processor.
    #[Groups(['write:Cloturer'])]
    public ?\DateTimeImmutable $datearriveereelle = null;
}
