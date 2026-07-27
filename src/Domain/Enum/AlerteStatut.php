<?php

namespace App\Domain\Enum;

/**
 * Cycle de vie d'une alerte persistée :
 *  - ACTIVE  : détectée, non encore prise en compte (compte dans le badge de la cloche) ;
 *  - LUE     : acquittée par un agent, mais la situation persiste encore (reste au listing) ;
 *  - RESOLUE : la situation a disparu (auto-résolution par le balayeur) — sort de la cloche.
 */
enum AlerteStatut: string
{
    case ACTIVE  = 'ACTIVE';
    case LUE     = 'LUE';
    case RESOLUE = 'RESOLUE';

    /**
     * Statuts « vivants » : une alerte existe déjà pour une situation tant qu'elle est ACTIVE
     * ou LUE. Le balayeur ne recrée pas par-dessus (déduplication) et bascule en RESOLUE quand
     * la situation n'est plus calculée.
     *
     * @return string[]
     */
    public static function nonResolus(): array
    {
        return [self::ACTIVE->value, self::LUE->value];
    }
}
