<?php

namespace App\Entity\Output\Fidelite;

final class TopMembreDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly ?string $contact,
        public readonly int $voyages,                 // voyages éligibles cumulés depuis l'adhésion
        public readonly int $recompensesUtilisees,
        public readonly int $progression,             // tampons de la carte en cours (0..seuil-1)
        public readonly bool $recompenseDisponible
    )
    {
    }
}
