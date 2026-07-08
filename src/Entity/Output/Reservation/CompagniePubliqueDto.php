<?php

namespace App\Entity\Output\Reservation;

/** Branding public d'une compagnie (page d'accueil du tunnel de réservation invité). */
final class CompagniePubliqueDto
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $libelle,
        public readonly ?string $sigle,
        public readonly ?string $contact,
        public readonly ?string $siteweb
    )
    {
    }
}
