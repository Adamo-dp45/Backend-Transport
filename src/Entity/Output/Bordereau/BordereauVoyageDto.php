<?php

namespace App\Entity\Output\Bordereau;

final class BordereauVoyageDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $codevoyage,
        /** Numéro de départ DU JOUR, tel qu'imprimé sur le billet (« DÉPART 4 »). */
        public readonly ?int $numerodepart,
        public readonly string $provenance,
        public readonly string $destination,
        public readonly string $datedepartprevue,
        public readonly int $placestotal,
        public readonly int $placesoccupees
    )
    {
    }
}