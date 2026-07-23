<?php

namespace App\Entity\Output\Bordereau;

final class BordereauGareDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly string $ville,
        // Horaires de passage du car à CETTE gare (null si non renseigné / pas encore passé).
        public readonly ?string $heurePrevue = null,       // heure de passage prévue
        public readonly ?string $arriveeReelle = null,     // arrivée réelle du car
        public readonly ?string $departReelle = null,      // départ réel du car
        public readonly ?int $retardMinutes = null,        // réel − prévu (négatif = en avance)
        public readonly ?int $tempsArretMinutes = null,    // départ − arrivée (temps à quai)
    )
    {
    }
}
