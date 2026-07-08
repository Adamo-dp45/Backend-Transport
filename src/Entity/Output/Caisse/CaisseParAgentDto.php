<?php

namespace App\Entity\Output\Caisse;

final class CaisseParAgentDto
{
    public function __construct(
        public readonly int $agentId,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int $nbtickets,
        public readonly float  $recetteTickets,
        public readonly int $nbcourriers,
        public readonly float  $recetteCourriers,
        public readonly int $nbbagages,
        public readonly float  $recetteBagages,
        public readonly float  $recetteTotale,
        /** @var CaisseDetailVoyageDto[] */
        public readonly array  $detailParVoyage
    )
    {
    }
}