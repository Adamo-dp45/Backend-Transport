<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Règles d'autorisation pour la gestion des utilisateurs (édition / suspension).
 *
 * Hiérarchie : super admin > fondateur > admin entreprise > admin de gare > utilisateur.
 *  - Personne ne se gère soi-même via l'administration (un profil dédié existe pour ça).
 *  - Le FONDATEUR gère tous les comptes de SA compagnie, administrateurs compris : c'est lui qui les
 *    nomme et les rétrograde, il doit donc pouvoir éditer leur fiche.
 *  - Le fondateur lui-même n'est gérable que par le super administrateur.
 *  - Les autres admins entreprise ne sont gérables que par le fondateur ou le super administrateur.
 *  - Un acteur non-admin (admin de gare OU utilisateur simple) ne peut gérer que les utilisateurs
 *    SIMPLES de SA gare : ni un admin de gare, ni un utilisateur d'une autre gare, et il doit
 *    lui-même être rattaché à une gare.
 */
class UserManagementGuard
{
    public function assertCanManage(User $actor, User $target): void
    {
        // Personne ne se gère soi-même ici
        if ($actor->getId() === $target->getId()) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas vous gérer vous-même ici (utilisez votre profil)');
        }

        // Un super administrateur ne peut être ni suspendu ni modifié par un AUTRE utilisateur
        // (même pas par un autre super admin) : il ne se gère que lui-même via son profil.
        // Placé AVANT le bypass super admin ci-dessous pour qu'il s'applique aussi à un acteur super admin.
        if (in_array('ROLE_SUPER_ADMIN', $target->getRoles(), true)) {
            throw new AccessDeniedHttpException('Un super administrateur ne peut être ni suspendu ni modifié par un autre utilisateur');
        }

        // Super admin : tout est permis (sur des cibles non super admin)
        if (in_array('ROLE_SUPER_ADMIN', $actor->getRoles(), true)) {
            return;
        }

        // Le fondateur lui-même : réservé au super admin
        if ($target->isFounder()) {
            throw new AccessDeniedHttpException('Le fondateur ne peut être géré que par le super administrateur');
        }

        /*
            Le FONDATEUR est le sommet de la hiérarchie DE SA COMPAGNIE : c'est lui, et lui seul, qui
            nomme et rétrograde ses administrateurs (PromouvoirUserProcessor le lui réserve). Refuser
            l'édition de leur fiche à celui qui décide de leur promotion n'avait pas de sens.

            Sûr à cet endroit : la cible n'est ni super admin (écarté plus haut), ni le fondateur
            lui-même (juste au-dessus), ni l'acteur (premier contrôle). Le périmètre entreprise est
            garanti par 'UserEntrepriseExtension', qui filtre AUSSI le chargement de l'item — un
            fondateur ne peut donc pas atteindre l'administrateur d'une autre compagnie.
        */
        if ($actor->isFounder()) {
            return;
        }

        // Les autres administrateurs d'entreprise : réservés au fondateur et au super admin
        if (in_array('ROLE_ADMIN', $target->getRoles(), true)) {
            throw new AccessDeniedHttpException('Un administrateur ne peut être géré que par le fondateur ou le super administrateur');
        }

        // Admin entreprise : gère les admins de gare et les utilisateurs
        if (in_array('ROLE_ADMIN', $actor->getRoles(), true)) {
            return;
        }

        // Acteur non-admin RATTACHÉ À UNE GARE (admin de gare ou utilisateur simple lié à une gare) :
        // périmètre limité à SA gare, et uniquement des utilisateurs simples (pas un admin de gare).
        if ($actor->getGare() !== null) {
            if (in_array('ROLE_ADMIN_GARE', $target->getRoles(), true)) {
                throw new AccessDeniedHttpException('Vous ne pouvez pas gérer un administrateur de gare');
            }
            if ($target->getGare()?->getId() !== $actor->getGare()->getId()) {
                throw new AccessDeniedHttpException('Vous ne pouvez gérer que les utilisateurs de votre gare');
            }
            return;
        }

        // Acteur non-admin SANS gare (utilisateur central disposant des permissions sur User) :
        // gère tout le monde sauf les admins entreprise/super et le fondateur (déjà exclus) et lui-même.
        if (in_array('ROLE_ADMIN_GARE', $actor->getRoles(), true)) {
            // anomalie : un admin de gare devrait toujours être rattaché à une gare
            throw new AccessDeniedHttpException('Un administrateur de gare doit être rattaché à une gare');
        }
    }

    public function canManage(User $actor, User $target): bool
    {
        try {
            $this->assertCanManage($actor, $target);
            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }
}
