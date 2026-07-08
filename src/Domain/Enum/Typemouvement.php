<?php

namespace App\Domain\Enum;

enum Typemouvement: string
{
    case ENTREE = 'ENTREE';

    case SORTIE = 'SORTIE';

    case AJUSTEMENT = 'AJUSTEMENT';
}