<?php

namespace App\Domain\Enum;

enum BagageStatus: string
{
    case STATUT_ENREGISTRE = 'ENREGISTRE'; // Le bagage est saisi dans le système, en attente de validation ou d'association à un ticket
    case STATUT_EMBARQUE = 'EMBARQUE'; // le bagage est accepté pour le transport, le reçu est émis et la vente est acquise
    case STATUT_LIVRE = 'LIVRE'; // le bagage a été remis au destinataire, Auto quand le voyage est clôturé
    case STATUT_PERDU = 'PERDU'; // le transport a bien eu lieu, mais un incident est survenu
    case STATUT_ANNULE = 'ANNULE'; // le transport n'a finalement pas été réalisé
}


