<?php

namespace App\Entity\Output\Courrier;

final class RecetteCourrierParTrajetDto
{
    public function __construct(
        public readonly string $trajet, // "Abidjan → Bouaké"
        public readonly float $montant,
        public readonly int $nbcourriers
    )
    {
    }
}