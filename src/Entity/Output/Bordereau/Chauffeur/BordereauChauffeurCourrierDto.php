<?php

namespace App\Entity\Output\Bordereau\Chauffeur;

final class BordereauChauffeurCourrierDto
{
    public function __construct(
        public readonly string $codecourrier,
        public readonly string $nomexpediteur,
        public readonly string $nomdestinataire,
        public readonly string $garedepart,
        public readonly string $garearrivee,
        public readonly int $nbcolis,
        public readonly float $montant,
        public readonly string $modepaiement
    )
    {
    }
}