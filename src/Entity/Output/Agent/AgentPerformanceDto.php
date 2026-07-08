<?php

namespace App\Entity\Output\Agent;

final class AgentPerformanceDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly int $nbtickets,
        public readonly float $recette,
        public readonly bool $actif
    )
    {
    }
}