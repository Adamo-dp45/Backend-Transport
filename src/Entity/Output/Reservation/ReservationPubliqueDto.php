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
        /** Numéro de départ DU JOUR, tel qu'imprimé sur le billet (« DÉPART 4 »). */
        public readonly ?int $numerodepart,
        /*
            Heure attendue du car À LA GARE DE MONTÉE du client (départ du voyage + durée de l'arrêt),
            donc l'heure à laquelle il doit être là — et celle qui borne 'dateexpiration'. Distincte
            de 'datedepartprevue', qui reste l'heure de départ du voyage depuis SON origine.
        */
        public readonly ?string $heurepassage,
        public readonly ?string $datedepartprevue,
        public readonly bool $bonDisponible,
        public readonly ?string $billetEmis,
        // --- Suivi « où est mon car » : position temps réel + retard estimé ---
        // Le car a-t-il quitté son origine (départ réel horodaté) ?
        public readonly bool $voyageDemarre,
        // Gare où se trouve actuellement le car (position courante), null tant qu'il n'est pas parti.
        public readonly ?string $positionActuelle,
        // Retard courant du car en minutes (positif = en retard, négatif = en avance), null si non mesurable.
        public readonly ?int $retardMinutes,
        // Heure de passage ESTIMÉE chez CE client = heure prévue + retard courant (null si non calculable).
        public readonly ?string $heurepassageEstimee,
        public readonly ?PaiementInfoDto $paiement = null
    )
    {
    }
}
