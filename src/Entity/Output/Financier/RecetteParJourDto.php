<?php

namespace App\Entity\Output\Financier;

final class RecetteParJourDto
{
    public function __construct(
        public readonly string $label,
        public readonly float $montant,
        /*
            Répartition du total par CANAL, pour empiler la courbe par type côté interface.
            La somme des quatre vaut exactement 'montant' : les courriers valent 0 quand l'entreprise
            les exclut du chiffre d'affaires (ConfigRecette.courriershorsca), sinon l'empilement
            ne totaliserait pas le montant affiché.
            Valeurs par défaut : le DTO reste instanciable sans sa répartition.
        */
        public readonly float $tickets = 0.0,
        public readonly float $reservations = 0.0,
        public readonly float $courriers = 0.0,
        public readonly float $bagages = 0.0
    )
    {
    }
}
