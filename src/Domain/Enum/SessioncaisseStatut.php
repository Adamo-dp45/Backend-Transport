<?php

namespace App\Domain\Enum;

/**
 * DEUX états seulement, et pas de retour en arrière.
 *
 * Une session clôturée est une PIÈCE OPPOSABLE : l'agent a compté son tiroir et signé l'écart. La
 * rouvrir permettrait de récrire ce constat après coup — si l'agent reprend la vente, sa première
 * écriture lui ouvre une NOUVELLE session et la clôture précédente reste intacte.
 *
 * Pas de troisième état « visée par le chef de gare » : le contrôle hiérarchique se fait par la
 * LECTURE (écran des sessions filtrable sur les écarts, alerte 'CAISSE_ECART_ELEVE' en portée
 * DIRECTION). Un statut de plus, c'est un geste de plus à ne pas oublier au guichet.
 */
enum SessioncaisseStatut: string
{
    case OUVERTE = 'OUVERTE';   // L'agent encaisse : les ventes s'y rattachent, rien n'est figé
    case CLOTUREE = 'CLOTUREE'; // Tiroir compté, totaux GELÉS, écart motivé — plus aucune écriture
}
