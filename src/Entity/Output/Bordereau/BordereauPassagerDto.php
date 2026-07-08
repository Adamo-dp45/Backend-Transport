<?php

namespace App\Entity\Output\Bordereau;

final class BordereauPassagerDto
{
    public function __construct(
        public readonly string $codeticket,
        public readonly ?string $nomclient,
        public readonly ?string $contactclient,
        public readonly float  $prix,
        public readonly int $siegenumero,
        public readonly string $createdat
    )
    {
    }
}