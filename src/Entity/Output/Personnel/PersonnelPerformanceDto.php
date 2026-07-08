<?php

namespace App\Entity\Output\Personnel;

final class PersonnelPerformanceDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $type,
        public readonly int $nbvoyages,
        public readonly int $nbdepannages,
        public readonly bool $actif
    )
    {
    }
}