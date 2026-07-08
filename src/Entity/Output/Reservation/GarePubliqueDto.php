<?php

namespace App\Entity\Output\Reservation;

/** Une gare (provenance ou destination) exposée publiquement. */
final class GarePubliqueDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly ?string $ville
    )
    {
    }
}
