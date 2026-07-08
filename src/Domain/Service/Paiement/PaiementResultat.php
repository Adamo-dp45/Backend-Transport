<?php

namespace App\Domain\Service\Paiement;

/**
 * Résultat d'une tentative de paiement (succès/échec + référence du prestataire).
 */
final class PaiementResultat
{
    public function __construct(
        public readonly bool $succes,
        public readonly ?string $reference = null,
        public readonly ?string $message = null
    )
    {
    }
}
