<?php

namespace App\Domain\Enum;

enum ReferenceStatus: string
{
    case ACTIF = 'ACTIF';
    case SUSPENDU = 'SUSPENDU';
}