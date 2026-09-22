<?php

namespace App\Entity\Dto;

use App\Entity\Gare;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un administrateur consigne le passage d'une gare qui n'a jamais été pointée :
 * `PATCH /voyages/{id}/rattraper-passage`.
 *
 * L'HEURE EST OBLIGATOIRE, et c'est tout l'intérêt de l'action. Prendre l'instant de la correction
 * placerait l'arrivée après celle des gares déjà pointées : le tronçon mesuré ressortirait négatif
 * (écarté par `RecalageService`), le retard de la gare serait celui de l'oubli et non celui du car,
 * et l'« évolution du retard le long du trajet » deviendrait illisible. On répare un trou dans la
 * chronologie ; le seul moyen de ne pas en creuser un autre est de demander l'heure réelle.
 */
class RattrapagePassageInput
{
    /** La gare traversée dont l'arrivée n'a pas été consignée. */
    #[Groups(['write:RattrapagePassageInput'])]
    #[Assert\NotNull(message: 'La gare dont le passage est à rattraper est obligatoire')]
    public ?Gare $gare = null;

    /** Heure RÉELLE du passage du car à cette gare. */
    #[Groups(['write:RattrapagePassageInput'])]
    #[Assert\NotNull(message: 'L\'heure réelle du passage est obligatoire : c\'est elle que l\'on répare')]
    public ?\DateTimeImmutable $arrivee = null;
}
