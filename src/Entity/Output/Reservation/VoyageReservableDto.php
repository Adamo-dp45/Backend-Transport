<?php

namespace App\Entity\Output\Reservation;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Un voyage sur lequel une réservation peut ENCORE être créée, pour le sélecteur du guichet.
 *
 * Le front ne doit pas rejouer cette décision : elle dépend de la gare de l'agent, de l'avancement
 * réel du car et des durées de trajet par arrêt. Il la demandait auparavant à coups de filtres
 * ('datedepartprevue[after]', 'exists[datedepartreelle]'), ce qui redevenait faux à chaque évolution
 * de la règle — et l'est devenu dès qu'un car parti est resté réservable en aval.
 */
final class VoyageReservableDto
{
    public function __construct(
        #[Groups(['read:VoyageReservable'])]
        public readonly int $id,
        #[Groups(['read:VoyageReservable'])]
        public readonly ?string $codevoyage,
        /** Numéro de départ DU JOUR, tel qu'imprimé sur le billet (« DÉPART 4 »). */
        #[Groups(['read:VoyageReservable'])]
        public readonly ?int $numerodepart,
        #[Groups(['read:VoyageReservable'])]
        public readonly ?string $provenance,
        #[Groups(['read:VoyageReservable'])]
        public readonly ?string $destination,
        /** Heure de passage du car à la gare de l'agent (null pour un profil sans gare : admin/central). */
        #[Groups(['read:VoyageReservable'])]
        public readonly ?string $heurepassage = null,
        /** Départ du voyage depuis son origine — contexte, à ne pas confondre avec l'heure ci-dessus. */
        #[Groups(['read:VoyageReservable'])]
        public readonly ?string $datedepartprevue = null,
        /** Le car est-il déjà parti ? Le voyage reste réservable en aval, mais l'agent doit le savoir. */
        #[Groups(['read:VoyageReservable'])]
        public readonly bool $enRoute = false,
    )
    {
    }
}
