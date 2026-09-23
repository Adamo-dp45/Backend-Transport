<?php

namespace App\Domain\Trait;

use Symfony\Component\HttpFoundation\Request;

trait PeriodeTrait
{
    private function parsePeriode(?Request $request): array
    {
        $debut = $request?->query->get('debut');
        $fin = $request?->query->get('fin');

        /*
            !! '->setTime(0, 0)' SUR LE DÉBUT AUSSI. 'first day of this month' garde l'HEURE COURANTE
            (à 10 h 33, il vaut « le 1er à 10 h 33 ») : tout ce qui s'est passé le premier jour de la
            période avant l'heure de la consultation tombait hors du calcul. Silencieux et mouvant —
            le même écran ne donnait pas le même chiffre le matin et le soir, et la recette du 1er
            n'était complète qu'à minuit. La fin était déjà bornée à 23:59:59 ; le début ne l'était pas.
            Une date passée en paramètre est ramenée à son début de journée pour la même raison.
        */
        $dateDebut = ($debut ? new \DateTimeImmutable($debut) : new \DateTimeImmutable('first day of this month'))->setTime(0, 0);
        $dateFin = ($fin ? new \DateTimeImmutable($fin) : new \DateTimeImmutable('last day of this month'))->setTime(23, 59, 59);

        return [
            $dateDebut,
            $dateFin
            // $dateDebut->format('Y-m-d H:i:s'),
            // $dateFin->format('Y-m-d H:i:s')
        ];
    }
}