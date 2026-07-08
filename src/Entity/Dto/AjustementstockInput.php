<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class AjustementstockInput
{
    /*
        #[Assert\NotNull]
        #[Groups(['write:AjustementstockInput'])]
        public int $piece; -- On vas le récupérer dans l'url
    */

    #[Assert\NotNull]
    #[Groups(['write:AjustementstockInput'])]
    public int $quantite; // Ici 'quantite' peut être positive ou négative

    #[Assert\NotBlank]
    #[Groups(['write:AjustementstockInput'])]
    public string $motif;

}