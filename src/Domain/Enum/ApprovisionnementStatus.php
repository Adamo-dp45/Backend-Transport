<?php

namespace App\Domain\Enum;

enum ApprovisionnementStatus: string
{
    case VALIDE = 'VALIDE';
    case ANNULE = 'ANNULE'; // Approvisionnement annulé : stock retiré (inverse de l'entrée), exclu des coûts (reste visible)
}
