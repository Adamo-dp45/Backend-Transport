<?php

namespace App\Domain\Enum;

enum ReservationStatus: string
{
    case STATUT_EN_ATTENTE     = 'EN_ATTENTE';     // place tenue, en attente de paiement/confirmation
    case STATUT_CONFIRMEE      = 'CONFIRMEE';      // payée/confirmée (le billet est ou sera émis)
    case STATUT_A_REGULARISER  = 'A_REGULARISER';  // payée mais no-show à l'échéance → récupérable (report + pénalité)
    case STATUT_EXPIREE        = 'EXPIREE';        // définitivement perdue (impayée échue, ou fenêtre de régularisation dépassée)
    case STATUT_ANNULEE        = 'ANNULEE';        // annulée (client/agent) → place libérée

    /**
     * Statuts qui TIENNENT réellement une place (déduite de la capacité disponible), tant que
     * l'échéance n'est pas dépassée et qu'aucun billet n'a été émis :
     *  - CONFIRMEE  : payée — on a encaissé, la place est garantie jusqu'à l'heure de présentation ;
     *  - EN_ATTENTE : impayée — place tenue le temps COURT du paiement (delaiPaiementMinutes).
     *
     * Ce hold de paiement évite d'encaisser un client dont la place aurait été vendue entre sa
     * réservation et son paiement : sans lui, on refusait le paiement APRÈS prélèvement chez le
     * prestataire. Il expire tout seul (la capacité filtre sur 'dateexpiration').
     */
    public static function tenantsPlace(): array
    {
        return [
            self::STATUT_CONFIRMEE->value,
            self::STATUT_EN_ATTENTE->value,
        ];
    }
}
