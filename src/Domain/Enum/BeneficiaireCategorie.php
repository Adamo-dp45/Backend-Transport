<?php

namespace App\Domain\Enum;

enum BeneficiaireCategorie: string
{
    case ETUDIANT      = 'ETUDIANT';
    case ENFANT        = 'ENFANT';
    case TROISIEME_AGE = 'TROISIEME_AGE';
    case PERSONNEL     = 'PERSONNEL';
    case MILITAIRE     = 'MILITAIRE';
    case PARTENAIRE    = 'PARTENAIRE';
    case AUTRE         = 'AUTRE';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
