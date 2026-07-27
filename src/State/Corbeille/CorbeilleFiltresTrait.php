<?php

namespace App\State\Corbeille;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lecture commune des filtres de la corbeille depuis la query string :
 *   ?type[]=ticket&type[]=courrier  (ou ?type=ticket)  et  ?entreprise=3
 */
trait CorbeilleFiltresTrait
{
    /**
     * @return array{0: string[]|null, 1: int|null} [types|null, entreprise|null]
     */
    private function filtres(?Request $request): array
    {
        if ($request === null) {
            return [null, null];
        }

        $rawType = $request->query->all()['type'] ?? null;
        $types = ($rawType === null || $rawType === '')
            ? null
            : (is_array($rawType) ? array_values($rawType) : [$rawType]);

        $rawEnt = $request->query->get('entreprise');
        $entreprise = ($rawEnt !== null && $rawEnt !== '') ? (int) $rawEnt : null;

        return [$types, $entreprise];
    }
}
