<?php

namespace App\Entity\Output\Bordereau;

final class BordereauOutput
{
    public function __construct(
        public readonly BordereauVoyageDto $voyage,
        public readonly BordereauGareDto   $gare,
        public readonly int $nbtickets,
        public readonly float $recette,
        public readonly int $placesrestantes,
        public readonly string $generele,
        /** @var BordereauPassagerDto[] */
        public readonly array $passagers,
        // Occupation AU DÉPART de cette gare (tronçon qui part d'ici) : ce que le car emporte en sortant
        public readonly int $placesoccupeesdepart = 0,
        public readonly int $placeslibresdepart = 0,
        // Cargo déposé à cette gare pour ce voyage (comptes seuls, sans recette) : aide de réconciliation
        public readonly int $nbbagages = 0,
        public readonly int $nbcourriers = 0
    )
    {
    }
}