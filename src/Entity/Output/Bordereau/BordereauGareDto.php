<?php

namespace App\Entity\Output\Bordereau;

final class BordereauGareDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly string $ville
    )
    {
    }
}