<?php

namespace App\Entity\Output\Inventaire;

final class InventaireOutput
{
    public function __construct(
        public readonly int $id,
        public readonly string $typemouvement,
        public readonly string $referencetype,
        public readonly ?int $referenceid,
        public readonly int $quantite,
        public readonly string  $datemouvement,
        public readonly string  $createdAt,
        public readonly ?string $pieceName,
        public readonly ?int $createdBy,
        public readonly ?string $createdByNom,
        public readonly ?string $createdByPrenom
    )
    {
    }
}