<?php

namespace App\Domain\Service\Paiement;

use App\Entity\Reservation;

/**
 * Adaptateur de paiement SIMULÉ (aucun appel externe). Guichet ({@see payer}) : succès immédiat.
 * En ligne ({@see initier}) : pas de page hébergée (aucune vue back) → renvoie `estSimule=true` et
 * `url=null` ; c'est le FRONT qui affiche une étape de paiement simulée puis appelle le webhook.
 *
 * À remplacer par un vrai prestataire Mobile Money : une autre implémentation de
 * PaiementProviderInterface dont `initier()` appellera l'API du prestataire et renverra sa vraie URL.
 */
class PaiementSimuleProvider implements PaiementProviderInterface
{
    public function payer(Reservation $reservation): PaiementResultat
    {
        return new PaiementResultat(succes: true, reference: $this->genererReference(), message: 'Paiement simulé accepté');
    }

    public function initier(Reservation $reservation, string $returnUrl): PaiementSession
    {
        return new PaiementSession(url: null, reference: $this->genererReference(), estSimule: true);
    }

    private function genererReference(): string
    {
        return 'SIM-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
}
