<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * La nouvelle adresse d'un utilisateur, changée par le SUPER ADMIN.
 *
 * UN SEUL CHAMP, et c'est le but. Le `PATCH /users/{id}` général est fermé au super admin — « c'est
 * à l'admin de modifier son utilisateur, à cause des `userRoles` », dit sa garde. L'ouvrir pour
 * l'e-mail lui donnerait aussi les rôles, l'entreprise et la gare : exactement ce que cette
 * exclusion protège. On ouvre donc une porte étroite, qui ne peut rien faire d'autre.
 */
class ChangerEmailInput
{
    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Cette adresse e-mail n\'est pas valide.')]
    #[Groups(['write:User:email'])]
    public string $email = '';
}
