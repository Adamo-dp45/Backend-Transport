<?php

namespace App\Entity\Output\Reservation;

/**
 * Info de paiement renvoyée à la CRÉATION d'une réservation invité. `url` = page hébergée du
 * prestataire (le front y redirige) ; `estSimule` = true → pas de vrai prestataire, le front simule.
 */
final class PaiementInfoDto
{
    public function __construct(
        public readonly string $reference,
        public readonly ?string $url,
        public readonly bool $estSimule
    )
    {
    }
}
