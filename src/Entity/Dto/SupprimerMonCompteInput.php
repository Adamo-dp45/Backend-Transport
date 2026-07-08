<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class SupprimerMonCompteInput
{
    #[Assert\NotBlank(message: 'Le mot de passe est requis pour confirmer la suppression.')]
    #[Groups(['write:User:supprimer'])]
    public string $password;
}
