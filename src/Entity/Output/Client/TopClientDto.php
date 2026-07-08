<?php

namespace App\Entity\Output\Client;

final class TopClientDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly ?string $contact,
        public readonly int $nbBillets,
        public readonly int $depense,      // total payé (FCFA) sur la période
        public readonly bool $membre       // membre du programme de fidélité
    )
    {
    }
}
