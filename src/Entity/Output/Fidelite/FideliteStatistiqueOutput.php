<?php

namespace App\Entity\Output\Fidelite;

final class FideliteStatistiqueOutput
{
    public function __construct(
        public readonly int $totalClients,
        public readonly int $totalMembres,
        public readonly float $tauxAdhesion,           // % de clients membres
        public readonly int $adhesionsPeriode,         // nouvelles adhésions sur la période
        public readonly int $recompensesUtilisees,     // billets-récompense émis sur la période
        public readonly int $valeurRecompenses,        // FCFA offerts via la fidélité sur la période
        public readonly int $recompensesDisponibles,   // membres ayant une récompense à réclamer (état courant)
        public readonly int $seuil,
        public readonly int $recompensePourcentage,
        public readonly bool $programmeActif,
        /** @var TopMembreDto[] */
        public readonly array $topMembres
    )
    {
    }
}
