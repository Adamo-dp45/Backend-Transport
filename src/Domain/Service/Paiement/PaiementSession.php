<?php

namespace App\Domain\Service\Paiement;

/**
 * Session de paiement EN LIGNE initiée auprès du prestataire.
 *
 * - `url` : page de paiement hébergée du prestataire vers laquelle le FRONT redirige le client
 *   (null en mode simulé : pas de page réelle → le front affiche sa propre étape de simulation).
 * - `reference` : référence prestataire, utilisée pour rapprocher le paiement au webhook.
 * - `estSimule` : true tant qu'aucun vrai prestataire n'est branché (le front simule le paiement).
 */
final class PaiementSession
{
    public function __construct(
        public readonly ?string $url,
        public readonly string $reference,
        public readonly bool $estSimule = false
    )
    {
    }
}
