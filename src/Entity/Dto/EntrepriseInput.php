<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class EntrepriseInput
{
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2)]
    #[Groups(['write:EntrepriseInput'])]
    public ?string $libelle = null;

    #[Assert\NotBlank()]
    #[Assert\Length(min: 3)]
    #[Groups(['write:EntrepriseInput'])]
    public ?string $contact1 = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $contact2 = null;

    #[Assert\Length(min: 2)]
    #[Groups(['write:EntrepriseInput'])]
    public ?string $adresse = null;

    #[Groups(['write:EntrepriseInput'])]
    #[Assert\Email()]
    public ?string $email = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $anneecreation = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $sigle = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $siteweb = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $rccm = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $banque = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $type = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?string $centreimpot = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?int $tauxtva = null;

    #[Groups(['write:EntrepriseInput'])]
    public ?int $image = null;

}