<?php

namespace App\Domain\Enum;

enum DepannageStatus: string
{
    case EN_COURS = 'EN COURS';

    case CLOTURE = 'CLOTURE';

    case ANNULE = 'ANNULE'; // Dépannage annulé : stock restauré, exclu des coûts (reste visible pour l'audit)
}