<?php

namespace App\Domain\Enum;

enum CarStatus: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case EN_VOYAGE = 'EN_VOYAGE';
    case EN_PANNE = 'EN_PANNE';
    case EN_MAINTENANCE = 'EN_MAINTENANCE';
}