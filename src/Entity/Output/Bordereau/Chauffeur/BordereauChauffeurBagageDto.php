<?php

namespace App\Entity\Output\Bordereau\Chauffeur;

final class BordereauChauffeurBagageDto
{
    public function __construct(
        public readonly string $codebagage,
        public readonly string $nomclient,
        public readonly string $nature,
        public readonly ?string $type,
        public readonly int $poids,
        public readonly float $montant
    )
    {
    }
}