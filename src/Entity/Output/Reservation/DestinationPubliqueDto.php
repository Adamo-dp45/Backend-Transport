<?php

namespace App\Entity\Output\Reservation;

/** Une destination desservie depuis une gare + son tarif (issu de la grille tarifaire). */
final class DestinationPubliqueDto
{
    public function __construct(
        public readonly GarePubliqueDto $gare,
        public readonly int $montant
    )
    {
    }
}
