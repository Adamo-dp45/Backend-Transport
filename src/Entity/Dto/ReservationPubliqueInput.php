<?php

namespace App\Entity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Corps du POST de création d'une réservation invité (mobile/web-client). */
final class ReservationPubliqueInput
{
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 2, max: 255)]
    public ?string $nom = null;

    #[Assert\NotBlank(message: 'Le téléphone est obligatoire')]
    #[Assert\Length(min: 6, max: 30)]
    public ?string $contact = null;

    #[Assert\NotNull(message: 'Le voyage est obligatoire')]
    #[Assert\Positive]
    public ?int $voyage = null;

    #[Assert\NotNull(message: 'La gare de montée est obligatoire')]
    #[Assert\Positive]
    public ?int $montee = null;

    #[Assert\Positive]
    public ?int $descente = null;

    /** URL de retour du front après paiement (peut contenir le jeton {code}). */
    public ?string $returnUrl = null;
}
