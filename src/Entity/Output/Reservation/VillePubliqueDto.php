<?php

namespace App\Entity\Output\Reservation;

/** Une ville desservie (étape 1 du tunnel de réservation invité). */
final class VillePubliqueDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom
    )
    {
    }
}
