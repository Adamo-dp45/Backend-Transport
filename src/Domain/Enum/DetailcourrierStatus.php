<?php

namespace App\Domain\Enum;

enum DetailcourrierStatus: string
{
    case STATUT_NORMAL = 'NORMAL';
    case STATUT_PERDU = 'PERDU';
}