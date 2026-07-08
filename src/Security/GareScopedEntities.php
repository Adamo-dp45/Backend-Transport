<?php

namespace App\Security;

use App\Entity\User;

/**
 * Source unique de vérité du périmètre d'un acteur rattaché à une gare.
 *
 * Ces entités sont celles qu'un admin de gare (ou un utilisateur de gare avec les droits idoines)
 * peut réellement gérer — leurs données sont déjà bornées à sa gare par 'GareScopeExtension'.
 * Ce même périmètre sert à deux choses :
 *  - le bypass d'écriture du 'PermissionVoter' (il agit sur ces entités),
 *  - les permissions qu'il a le droit de DÉLÉGUER dans un rôle (on ne délègue pas ce qu'on ne possède pas).
 */
final class GareScopedEntities
{
    /** Noms courts des entités du périmètre d'un acteur de gare. */
    public const ENTITIES = ['Voyage', 'Ticket', 'Reservation', 'Courrier', 'Bagage', 'User', 'Role'];

    /** Admin/super entreprise : aucune restriction de périmètre. */
    public static function isPrivileged(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    /**
     * Acteur borné à une gare pour la délégation : non privilégié ET rattaché à une gare.
     * (Un utilisateur central sans gare n'est pas borné par ce périmètre.)
     */
    public static function isGareDelegate(User $user): bool
    {
        return !self::isPrivileged($user) && $user->getGare() !== null;
    }
}
