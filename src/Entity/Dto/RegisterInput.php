<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterInput
{
    #[Assert\NotBlank()]
    #[Assert\Email()]
    #[Groups('write:Register')]
    public ?string $email = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups('write:Register')]
    public ?string $nom = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups('write:Register')]
    public ?string $prenom = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères')]
    /*
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial'
        )]
    */
    #[Groups('write:Register')]
    public ?string $password = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 2)]
    #[Groups('write:Register')]
    public ?string $libelle = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 8)]
    #[Groups('write:Register')]
    public ?string $contact1 = null;

    #[Groups('write:Register')]
    public ?string $contact2 = null;

    #[Assert\Length(min: 2)]
    #[Groups('write:Register')]
    public ?string $adresse = null;

    #[Assert\Email()]
    #[Groups('write:Register')]
    public ?string $emailEntreprise = null;

    #[Groups('write:Register')]
    public ?string $anneecreation = null;

    #[Groups('write:Register')]
    public ?string $sigle = null;

    #[Groups('write:Register')]
    public ?string $siteweb = null;

    #[Groups('write:Register')]
    public ?string $rccm = null;

    #[Groups('write:Register')]
    public ?string $banque = null;

    #[Groups('write:Register')]
    public ?string $type = null;

    #[Groups('write:Register')]
    public ?string $centreimpot = null;

    #[Groups('write:Register')]
    public ?int $tauxtva = null;

}