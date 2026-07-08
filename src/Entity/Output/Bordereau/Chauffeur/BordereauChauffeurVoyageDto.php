<?php

namespace App\Entity\Output\Bordereau\Chauffeur;

final class BordereauChauffeurVoyageDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $codevoyage,
        public readonly string $provenance,
        public readonly string $destination,
        public readonly string $datedepartprevue,
        public readonly ?string $matricule,
        public readonly int $placestotal,
        public readonly int $placesoccupees
    )
    {
    }
}