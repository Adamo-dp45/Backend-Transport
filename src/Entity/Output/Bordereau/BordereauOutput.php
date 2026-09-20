<?php

namespace App\Entity\Output\Bordereau;

final class BordereauOutput
{
    public function __construct(
        public readonly BordereauVoyageDto $voyage,
        public readonly BordereauGareDto   $gare,
        public readonly int $nbtickets,
        public readonly float $recette,
        public readonly int $placesrestantes,
        public readonly string $generele,
        /** @var BordereauPassagerDto[] */
        public readonly array $passagers,
        // Occupation AU DÉPART de cette gare (tronçon qui part d'ici) : ce que le car emporte en sortant
        public readonly int $placesoccupeesdepart = 0,
        public readonly int $placeslibresdepart = 0,
        // Cargo déposé à cette gare pour ce voyage (comptes seuls, sans recette) : aide de réconciliation
        public readonly int $nbbagages = 0,
        public readonly int $nbcourriers = 0,
        /*
            Ventilation des billets de cette gare PAR DESTINATION, dans l'ordre des arrêts de la
            ligne (« Abidjan → Bouaké : 4 », puis « Abidjan → Korhogo : 6 »). Ordonnée par la
            géographie et non par le nombre : c'est dans cet ordre que le car s'arrête, donc dans
            cet ordre qu'on appelle les passagers à l'embarquement.

            @var BordereauDestinationDto[]
        */
        public readonly array $destinations = []
    )
    {
    }
}