<?php

namespace App\Domain\Enum;

enum CourrierStatus: string
{
    case STATUT_EN_ATTENTE = 'EN_ATTENTE'; // Le courrier est créé et pas encore embarqué
    case STATUT_EN_TRANSIT = 'EN_TRANSIT'; // !! est embarqué sur un voyage et en route
    case STATUT_RECEPTIONNE = 'RECEPTIONNE'; // !! est arrivé à la gare d'arrivée et en attente de récupération
    case STATUT_LIVRE = 'LIVRE'; // !! emis physiquement au destinataire
    case STATUT_PERDU = 'PERDU';
    case STATUT_ANNULE = 'ANNULE';
}