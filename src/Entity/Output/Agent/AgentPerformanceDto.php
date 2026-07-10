<?php

namespace App\Entity\Output\Agent;

/**
 * Performance d'un agent = ce qu'il a réellement ENCAISSÉ au guichet sur la période (billets + courriers +
 * bagages, ventes commerciales à bord exclues), avec le détail par voyage pour la billetterie. Remplace
 * l'ancien classement « guichet tickets seuls » (qui ne reflétait pas toutes ses ventes).
 */
final class AgentPerformanceDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int $nbtickets,
        public readonly float $recetteTickets,
        public readonly int $nbcourriers,
        public readonly float $recetteCourriers,
        public readonly int $nbbagages,
        public readonly float $recetteBagages,
        public readonly float $recetteTotale,
        /** @var AgentDetailVoyageDto[] */
        public readonly array $detailParVoyage
    )
    {
    }
}
