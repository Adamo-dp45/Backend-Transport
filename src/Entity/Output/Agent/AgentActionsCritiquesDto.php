<?php

namespace App\Entity\Output\Agent;

/**
 * Profil « actions critiques » d'un agent sur la période — traçabilité anti-fraude :
 *  - taux d'annulation de SES ventes (billets émis annulés / billets émis) ;
 *  - annulations qu'il a effectuées (billets / bagages / courriers) ;
 *  - suppressions qu'il a effectuées (billets / bagages / courriers).
 */
final class AgentActionsCritiquesDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int $ventesBillets,
        public readonly int $ventesAnnulees,
        public readonly float $tauxAnnulation,
        public readonly int $annulTickets,
        public readonly int $annulBagages,
        public readonly int $annulCourriers,
        public readonly int $supprTickets,
        public readonly int $supprBagages,
        public readonly int $supprCourriers,
    )
    {
    }
}
