<?php

namespace App\Entity\Output\Reservation;

/** Un départ réservable (voyage daté) sur un tronçon : date, places restantes et prix. */
final class DepartPubliqueDto
{
    public function __construct(
        public readonly int $voyageId,
        public readonly ?string $codevoyage,
        public readonly ?string $datedepartprevue,
        public readonly ?string $datearriveeprevue,
        public readonly int $placesDisponibles,
        public readonly ?int $montant
    )
    {
    }
}
