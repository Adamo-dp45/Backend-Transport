<?php

namespace App\Entity\Interface;

/**
 * Périmètre C : l'entité appartient à UNE gare. Un agent rattaché à une gare ne voit que les lignes dont ce champ pointe vers SA gare. Ex. User.gare, Ticket.gare (gare de montée).
 */
interface GareOwnedInterface
{
    /** Nom du champ Doctrine (relation ManyToOne vers Gare) servant au filtrage par gare. */
    public static function gareScopeField(): string;
}
