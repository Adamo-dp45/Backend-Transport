<?php

namespace App\Domain\Trait;

use Symfony\Component\HttpFoundation\Request;

trait PeriodeTrait
{
    private function parsePeriode(?Request $request): array
    {
        $debut = $request?->query->get('debut');
        $fin = $request?->query->get('fin');

        $dateDebut = $debut ? new \DateTimeImmutable($debut) : new \DateTimeImmutable('first day of this month');
        $dateFin = ($fin ? new \DateTimeImmutable($fin) : new \DateTimeImmutable('last day of this month'))->setTime(23, 59, 59);

        return [
            $dateDebut,
            $dateFin
            // $dateDebut->format('Y-m-d H:i:s'),
            // $dateFin->format('Y-m-d H:i:s')
        ];
    }
}