<?php

namespace App\Entity\Output\Reservation;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Un départ sur lequel une réservation no-show peut effectivement être REPORTÉE.
 *
 * Le guichet ne doit pas reconstituer cette liste : elle dépend du tronçon de la réservation, des
 * lignes qui le desservent, de l'avancement réel de chaque car et des durées de trajet par arrêt.
 * Reconstituée côté front, elle divergeait dans les deux sens — masquant des cibles valables pour
 * une montée en aval, et en proposant d'autres que la régularisation refusait ensuite.
 */
final class ReportPossibleDto
{
    public function __construct(
        #[Groups(['read:ReportPossible'])]
        public readonly int $voyageId,
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $codevoyage,
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $provenance,
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $destination,
        /** Passage du car à la gare de montée DE LA RÉSERVATION : l'heure qui concerne ce client. */
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $heurepassage,
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $datedepartprevue,
        /** Matricule du car, ou null : le billet ne pourra être émis qu'une fois un car affecté. */
        #[Groups(['read:ReportPossible'])]
        public readonly ?string $car,
        /** Le car est déjà parti de son origine — reste reportable si la montée est en aval. */
        #[Groups(['read:ReportPossible'])]
        public readonly bool $enRoute,
        /** Décompte que la régularisation encaissera : calculé ici, pas à l'écran. */
        #[Groups(['read:ReportPossible'])]
        public readonly int $penalite,
        #[Groups(['read:ReportPossible'])]
        public readonly int $complement,
        #[Groups(['read:ReportPossible'])]
        public readonly int $total,
    )
    {
    }
}
