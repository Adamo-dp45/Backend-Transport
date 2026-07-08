<?php

namespace App\Entity\Output\Bordereau\Chauffeur;

final class BordereauChauffeurTicketDto
{
    public function __construct(
        public readonly string $codeticket,
        public readonly ?string $nomclient,
        public readonly ?string $contactclient,
        public readonly int $siegenumero,
        public readonly float $prix
    )
    {
    }
}