<?php

namespace App\Entity\Output\Bordereau\Chauffeur;

final class BordereauChauffeurOutput
{
    public function __construct(
        public readonly BordereauChauffeurVoyageDto $voyage,
        public readonly string $generele,
        public readonly int $totalTickets,
        public readonly int $totalCourriers,
        public readonly int $totalBagages,
        /** @var BordereauChauffeurTicketDto[] */
        public readonly array $tickets,
        /** @var BordereauChauffeurCourrierDto[] */
        public readonly array $courriers,
        /** @var BordereauChauffeurBagageDto[] */
        public readonly array $bagages
    )
    {
    }
}