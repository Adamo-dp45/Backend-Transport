<?php

namespace App\Entity\Output\Bordereau;

/**
 * Une ligne du récapitulatif par destination : « Abidjan → Bouaké : 4 ».
 *
 * La gare de DÉPART est celle du bordereau — elle est la même sur toutes les lignes — mais elle est
 * portée ici plutôt que déduite à l'affichage : le document est signé et archivé, chaque ligne doit
 * se lire seule, y compris recopiée à la main sur un cahier de gare.
 */
final class BordereauDestinationDto
{
    public function __construct(
        public readonly string $depart,
        public readonly string $destination,
        public readonly int $nbtickets
    )
    {
    }
}
