<?php

namespace App\Domain\Enum;

/**
 * Comment une dépense a été réglée.
 *
 * UNE ÉTIQUETTE, ET RIEN DE PLUS — corrigé le 28/09/2026. Ce champ a longtemps été présenté comme le
 * crochet de la future CAISSE : « `ESPECES` reliera la dépense au tiroir, les autres modes n'y touchent
 * pas ». C'était une erreur de cadrage. Le SOLDE d'une gare ou d'une entreprise n'est pas un coffre :
 * c'est l'argent qu'elle DÉTIENT, où qu'il soit — coffre, compte en banque, portefeuille mobile. Une
 * dépense le fait donc baisser QUEL QUE SOIT son mode de règlement.
 *
 * Le mode reste utile pour ce qu'il est : savoir COMMENT on a payé, ventiler les règlements à l'écran,
 * et retrouver une pièce. Il ne décide d'aucun total.
 *
 * !! NE PAS REMETTRE DE FILTRE `= ESPECES` DANS UN CALCUL DE SOLDE. Il ferait disparaître des sorties
 * d'argent bien réelles, et le solde deviendrait systématiquement trop haut — sans que rien ne le dise.
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
