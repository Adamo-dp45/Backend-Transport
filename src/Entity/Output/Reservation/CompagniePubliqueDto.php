<?php

namespace App\Entity\Output\Reservation;

/** Branding public d'une compagnie (page d'accueil du tunnel de réservation invité). */
final class CompagniePubliqueDto
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $libelle,
        public readonly ?string $sigle,
        public readonly ?string $contact,
        public readonly ?string $siteweb,
        /*
            Durée (minutes) pendant laquelle une réservation NON PAYÉE tient sa place, telle que
            configurée par la compagnie. Exposée pour que le client sache, AVANT de réserver, de
            combien de temps il dispose pour payer — sans quoi chaque app devrait deviner ce délai.
        */
        public readonly int $delaiPaiementMinutes
    )
    {
    }
}
