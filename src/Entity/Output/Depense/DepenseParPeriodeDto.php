<?php

namespace App\Entity\Output\Depense;

/** Total des dépenses d'un mois (label « AAAA-MM ») — l'évolution des charges. */
final class DepenseParPeriodeDto
{
    public function __construct(
        public readonly string $label,
        public readonly int $montant
    )
    {
    }
}
