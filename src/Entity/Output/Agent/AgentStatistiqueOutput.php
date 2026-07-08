<?php

namespace App\Entity\Output\Agent;

final class AgentStatistiqueOutput
{
    public function __construct(
        public readonly int $totalAgents,
        public readonly int $agentsActifs,
        /** @var AgentPerformanceDto[] */
        public readonly array $performances
    )
    {
    }
}