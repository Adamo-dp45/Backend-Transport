<?php

namespace App\Entity\Output\Bordereau;

final class BordereauVoyageDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $codevoyage,
        public readonly string $provenance,
        public readonly string $destination,
        public readonly string $datedepartprevue,
        public readonly int $placestotal,
        public readonly int $placesoccupees
    )
    {
    }
}