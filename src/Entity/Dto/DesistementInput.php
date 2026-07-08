<?php

namespace App\Entity\Dto;

use App\Entity\Siege;
use App\Entity\Voyage;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entrée d'un désistement de billet.
 *  - mode REPORT     : le client est reporté sur un autre voyage de la MÊME ligne. Le tronçon et le prix
 *                      d'origine sont conservés ; un NOUVEAU billet est créé et lié au billet d'origine.
 *  - mode ANNULATION : le billet est annulé et remboursé intégralement.
 *
 * Dans les deux cas, le billet d'origine passe REPORTE/ANNULE → son siège est libéré
 * (les requêtes d'occupation ne comptent que les billets VALIDE).
 */
class DesistementInput
{
    #[Groups(['write:DesistementInput'])]
    #[Assert\NotBlank(message: 'Le mode de désistement est obligatoire')]
    #[Assert\Choice(choices: ['REPORT', 'ANNULATION'], message: 'Mode de désistement invalide (REPORT ou ANNULATION)')]
    public ?string $mode = null;

    // -- Champs REPORT uniquement -- //

    #[Groups(['write:DesistementInput'])]
    public ?Voyage $voyage = null; // voyage cible (doit être de la même ligne que le billet d'origine)

    #[Groups(['write:DesistementInput'])]
    public ?Siege $siege = null;   // siège choisi sur le car du voyage cible

    // -- Commun -- //

    #[Groups(['write:DesistementInput'])]
    public ?string $motif = null;  // motif du désistement (traçabilité)
}
