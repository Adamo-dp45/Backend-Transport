<?php

namespace App\Entity\Interface;

/**
 * Périmètre B (cas ligne) : l'entité est rattachée à une ligne (directement ou via une relation).
 * Un agent la voit si la ligne au bout du chemin DESSERT sa gare (un de ses arrêts pointe vers sa gare).
 * Ex. Voyage : ['ligne'] ; Ticket : ['voyage', 'ligne'].
 */
interface LigneGareScopedInterface
{
    /**
     * Chemin de relations Doctrine menant à la Ligne, depuis l'entité.
     * @return string[]
     */
    public static function ligneScopePath(): array;
}
