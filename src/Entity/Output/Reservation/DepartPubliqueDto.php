<?php

namespace App\Entity\Output\Reservation;

/** Un départ réservable sur un tronçon : heures, places restantes et prix. */
final class DepartPubliqueDto
{
    public function __construct(
        public readonly int $voyageId,
        public readonly ?string $codevoyage,
        /** Numéro de départ DU JOUR, tel qu'imprimé sur le billet (« DÉPART 4 »). */
        public readonly ?int $numerodepart,
        /*
            Heure attendue du car À LA GARE DE MONTÉE demandée — c'est l'heure qui concerne le client,
            et celle sur laquelle son échéance est calée. Sur Abidjan → Bouaké → Korhogo, qui réserve
            au départ de Bouaké n'a que faire de l'heure à laquelle le car quitte Abidjan.

            Elle est calculée ICI, jamais par le client : les applications consommatrices (mobile, et
            tout futur client de l'API) n'ont pas à refaire l'arithmétique des durées d'arrêt.
        */
        public readonly ?string $heurepassage,
        /** Départ du voyage depuis son origine — information de contexte, pas l'heure du client. */
        public readonly ?string $datedepartprevue,
        public readonly ?string $datearriveeprevue,
        public readonly int $placesDisponibles,
        public readonly ?int $montant
    )
    {
    }
}
