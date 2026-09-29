<?php

namespace App\Entity\Output\Agent;

final class AgentStatistiqueOutput
{
    public function __construct(
        public readonly int $totalAgents,
        public readonly int $agentsActifs,
        /** @var AgentPerformanceDto[] */
        public readonly array $performances,
        /** @var AgentActionsCritiquesDto[] */
        public readonly array $actionsCritiques = [],
        /**
         * Recette d'écritures SANS AUTEUR (`created_by` nul), donc attribuable à personne.
         *
         * Elle est écartée du classement — ce n'est l'œuvre d'aucun agent —, mais SERVIE À PART plutôt
         * que jetée en silence : sans cette ligne, la somme des performances ne totaliserait pas la
         * recette de guichet et rien ne le dirait. Vaut zéro dans la vie normale ; un chiffre non nul
         * signale une écriture automatique ou une reprise de données à regarder.
         */
        public readonly float $recetteNonAttribuee = 0.0
    )
    {
    }
}