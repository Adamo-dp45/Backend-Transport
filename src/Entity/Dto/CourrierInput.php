<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class CourrierInput
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:CourrierInput'])]
    public string $nomexpediteur;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:CourrierInput'])]
    public string $contactexpediteur;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:CourrierInput'])]
    public string $nomdestinataire;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['write:CourrierInput'])]
    public string $contactdestinataire;

    // Gare de départ : FORCÉE à la gare de l'agent côté processor ; un utilisateur central (sans gare)
    // doit la fournir. On la laisse nullable ici, l'obligation est vérifiée dans CourrierProcessor.
    #[Groups(['write:CourrierInput'])]
    public ?int $gareDepart = null;

    // Gare d'arrivée : OBLIGATOIRE dès la création (la destination définit le colis).
    #[Assert\NotNull(message: 'La gare d\'arrivée est obligatoire')]
    #[Groups(['write:CourrierInput'])]
    public ?int $gareArrivee = null;

    // Voyage : OPTIONNEL (affecté à la création ou plus tard).
    #[Groups(['write:CourrierInput'])]
    public ?int $voyage = null;

    #[Groups(['write:CourrierInput'])]
    public ?int $fraissuivi = null;

    #[Assert\Choice(choices: ['ENVOI', 'RECEPTION'])]
    #[Groups(['write:CourrierInput'])]
    public string $modepaiement = 'ENVOI';

    #[Assert\NotNull]
    #[Assert\Count(min: 1)]
    #[Groups(['write:CourrierInput'])]
    public array $details = [];
}