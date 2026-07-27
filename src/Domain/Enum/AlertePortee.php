<?php

namespace App\Domain\Enum;

/**
 * Audience d'une alerte (à qui elle s'adresse) :
 *  - GARE       : opérationnel local, rattachée à 'idgare' (agents + admin de CETTE gare, plus les admins entreprise/central) ;
 *  - ENTREPRISE : partagée, visible par tous les utilisateurs de l'entreprise (stock, flotte) ;
 *  - DIRECTION  : sensible, réservée aux admins entreprise / central / super (anti-fraude, financier).
 *
 * La règle de visibilité concrète est portée par AlerteAudienceResolver (source unique,
 * utilisée par l'extension Doctrine ET les requêtes repo des contrôleurs).
 */
enum AlertePortee: string
{
    case GARE       = 'GARE';
    case ENTREPRISE = 'ENTREPRISE';
    case DIRECTION  = 'DIRECTION';
}
