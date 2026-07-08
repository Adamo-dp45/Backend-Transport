<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ParametreReservationInput
{
    #[Groups(['write:ParametreReservationInput'])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le délai ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 10080, message: 'Le délai ne peut pas dépasser 7 jours (10080 min)')]
    public ?int $delaiExpirationMinutes = null;

    #[Groups(['write:ParametreReservationInput'])]
    #[Assert\NotNull]
    #[Assert\Choice(choices: ['AUCUNE', 'FIXE', 'POURCENTAGE'], message: 'Type de pénalité invalide')]
    public ?string $penaliteType = 'AUCUNE';

    #[Groups(['write:ParametreReservationInput'])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'La pénalité ne peut pas être négative')]
    public ?int $penaliteValeur = 0;

    #[Groups(['write:ParametreReservationInput'])]
    #[Assert\NotNull]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'La fenêtre ne peut pas être négative')]
    #[Assert\LessThanOrEqual(value: 365, message: 'La fenêtre ne peut pas dépasser 365 jours')]
    public ?int $fenetreRegularisationJours = 7;
}
