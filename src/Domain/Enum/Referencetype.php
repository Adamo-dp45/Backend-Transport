<?php

namespace App\Domain\Enum;

enum Referencetype: string
{
    case APPROVISIONNEMENT = 'APPROVISIONNEMENT';

    case DEPANNAGE = 'DEPANNAGE';

    case AJUSTEMENT = 'AJUSTEMENT';
}