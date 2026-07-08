<?php

namespace App\Domain\Service\Paiement;

use App\Entity\Reservation;

/**
 * Abstraction du paiement Mobile Money d'une réservation.
 *
 * Permet de brancher plus tard un vrai prestataire (CinetPay, PayDunya, opérateurs…) sans toucher
 * à la logique de confirmation : il suffira de fournir une autre implémentation. L'adaptateur courant
 * ({@see PaiementSimuleProvider}) simule un paiement réussi.
 */
interface PaiementProviderInterface
{
    /**
     * Paiement IMMÉDIAT (guichet, espèces encaissées par l'agent) : le résultat est connu tout de suite.
     */
    public function payer(Reservation $reservation): PaiementResultat;

    /**
     * Paiement EN LIGNE (Mobile Money, invité mobile/web) : initie une session de paiement et renvoie
     * la référence + éventuellement l'URL de la page hébergée du prestataire. La confirmation arrive
     * plus tard, de façon asynchrone, via le webhook (POST public). Aucune vue n'est rendue par le
     * back : le FRONT redirige vers l'URL (ou simule si `estSimule`). $returnUrl = page de retour du front.
     */
    public function initier(Reservation $reservation, string $returnUrl): PaiementSession;
}
