<?php

namespace App\Domain\Enum;

/**
 * Comment une dépense a été réglée.
 *
 * Ce n'est pas qu'une étiquette : `ESPECES` est ce qui, demain, reliera une dépense à la CAISSE
 * d'une gare (l'argent sort du tiroir), quand les autres modes n'y touchent pas. D'où un champ
 * dédié plutôt qu'une mention dans le libellé.
 */
enum Modereglement: string
{
    case ESPECES = 'ESPECES';
    case MOBILE_MONEY = 'MOBILE_MONEY';
    case VIREMENT = 'VIREMENT';
    case CHEQUE = 'CHEQUE';

    /** Libellés d'affichage, pour les sélecteurs et les documents. */
    public function libelle(): string
    {
        return match ($this) {
            self::ESPECES => 'Espèces',
            self::MOBILE_MONEY => 'Mobile Money',
            self::VIREMENT => 'Virement',
            self::CHEQUE => 'Chèque',
        };
    }
}
