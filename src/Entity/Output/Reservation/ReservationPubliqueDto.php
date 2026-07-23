<?php

namespace App\Entity\Output\Reservation;

/**
 * Réservation invité renvoyée à la CRÉATION (avec le bloc `paiement`) et au SUIVI (`paiement` = null).
 * Données publiques uniquement (aucune donnée interne). Le bon/billet est rendu par le FRONT.
 */
final class ReservationPubliqueDto
{
    public function __construct(
        public readonly string $code,
        public readonly string $statut,
        public readonly string $etatpaiement,
        public readonly ?string $nomclient,
        public readonly ?string $contactclient,
        public readonly ?int $montant,
        public readonly ?string $dateexpiration,
        public readonly ?string $montee,
        public readonly ?string $descente,
        public readonly ?string $codevoyage,
        /*
            Heure attendue du car À LA GARE DE MONTÉE du client (départ du voyage + durée de l'arrêt),
            donc l'heure à laquelle il doit être là — et celle qui borne 'dateexpiration'. Distincte
            de 'datedepartprevue', qui reste l'heure de départ du voyage depuis SON origine.
        */
        public readonly ?string $heurepassage,
        public readonly ?string $datedepartprevue,
        public readonly bool $bonDisponible,
        public readonly ?string $billetEmis,
        public readonly ?PaiementInfoDto $paiement = null
    )
    {
    }
}
