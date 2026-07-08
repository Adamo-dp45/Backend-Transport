<?php

namespace App\Security;

use App\Entity\Gare;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Garde d'APPARTENANCE par gare pour les actions opérationnelles (livraison, perte, annulation,
 * modification/suppression d'un ticket…).
 *
 * Principe : un agent RATTACHÉ à une gare ne peut agir que sur les objets de SA gare. Les admins
 * entreprise/super et les utilisateurs « centraux » sans gare ne sont pas restreints (cohérent avec
 * 'GareScopeExtension' et 'TicketProcessor' : la contrainte ne s'applique que si l'utilisateur a une gare).
 */
class GareGuard
{
    public function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true)
            || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    /**
     * Vérifie que l'utilisateur est rattaché à la gare $cible. Admin et utilisateur central (sans gare)
     * passent ; un agent de gare doit être exactement sur $cible.
     */
    public function assertEstGare(User $user, ?Gare $cible, string $message): void
    {
        if ($this->isAdmin($user)) {
            return;
        }
        $gare = $user->getGare();
        if ($gare === null) {
            return; // utilisateur central sans gare : pas de restriction de gare
        }
        if ($cible === null || $gare->getId() !== $cible->getId()) {
            throw new BadRequestHttpException($message);
        }
    }
}
