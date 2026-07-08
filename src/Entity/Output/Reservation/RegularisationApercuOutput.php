<?php

namespace App\Entity\Output\Reservation;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Aperçu du décompte d'une régularisation (report d'une réservation no-show sur un nouveau départ) :
 * montant de la pénalité + complément tarifaire à encaisser, avant confirmation par l'agent.
 */
class RegularisationApercuOutput
{
    public function __construct(
        #[Groups(['read:RegularisationApercu'])]
        public readonly bool $possible,
        #[Groups(['read:RegularisationApercu'])]
        public readonly ?string $message = null,
        #[Groups(['read:RegularisationApercu'])]
        public readonly int $penalite = 0,
        #[Groups(['read:RegularisationApercu'])]
        public readonly int $complement = 0,
        #[Groups(['read:RegularisationApercu'])]
        public readonly int $prixInitial = 0,
        #[Groups(['read:RegularisationApercu'])]
        public readonly int $nouveauPrix = 0,
        #[Groups(['read:RegularisationApercu'])]
        public readonly int $total = 0,
    ) {
    }
}
