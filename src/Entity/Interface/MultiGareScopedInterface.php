<?php

namespace App\Entity\Interface;

/**
 * Périmètre B (cas 2 gares) : l'entité concerne plusieurs gares. Un agent la voit si l'UNE de ces
 * gares est la sienne. Ex. Courrier [garedepart, garearrivee], Bagage [garedepart, garedescente].
 */
interface MultiGareScopedInterface
{
    /**
     * Champs gare (relations ManyToOne) ; visible si l'un d'eux pointe vers la gare de l'agent.
     * @return string[]
     */
    public static function gareScopeFields(): array;
}
