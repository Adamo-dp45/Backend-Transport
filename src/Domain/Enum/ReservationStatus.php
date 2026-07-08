<?php

namespace App\Domain\Enum;

enum ReservationStatus: string
{
    case STATUT_EN_ATTENTE     = 'EN_ATTENTE';     // place tenue, en attente de paiement/confirmation
    case STATUT_CONFIRMEE      = 'CONFIRMEE';      // payée/confirmée (le billet est ou sera émis)
    case STATUT_A_REGULARISER  = 'A_REGULARISER';  // payée mais no-show à l'échéance → récupérable (report + pénalité)
    case STATUT_EXPIREE        = 'EXPIREE';        // définitivement perdue (impayée échue, ou fenêtre de régularisation dépassée)
    case STATUT_ANNULEE        = 'ANNULEE';        // annulée (client/agent) → place libérée

    /** Statuts qui « tiennent » encore une place (comptent dans la capacité). */
    public static function actifs(): array
    {
        return [self::STATUT_EN_ATTENTE->value];
    }
}
