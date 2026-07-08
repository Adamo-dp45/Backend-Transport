<?php

namespace App\Domain\Enum;

enum TicketStatus: string
{
    case STATUT_VALIDE = 'VALIDE';   // Billet actif : occupe un siège et compte dans la recette
    case STATUT_REPORTE = 'REPORTE'; // Le client s'est désisté et a été reporté sur un autre voyage (siège libéré)
    case STATUT_ANNULE = 'ANNULE';   // Le client s'est désisté avec remboursement intégral (siège libéré)
}
