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
    /**
     * Noms courts des entités du périmètre d'un acteur de gare.
     *
     * !! DUPLIQUÉE à deux endroits qui doivent suivre : 'ApiUser::hasPermission()' côté FT (qui
     * rejoue cette décision pour masquer un bouton) et '_gareScopedEntities' dans
     * 'commercialflutter'. Une liste qui diverge fait promettre à l'écran ce que le serveur refuse.
     *
     * 'Depense' en fait partie : l'admin de gare TIENT LES CHARGES DE SA GARE (carburant, péage,
     * imprévus) comme il tient ses ventes, sans qu'un administrateur d'entreprise ait à lui
     * fabriquer un rôle. Ses données sont déjà bornées à sa gare par 'GareScopeExtension', et les
     * charges du SIÈGE lui restent invisibles (gare nulle). La mise en corbeille, elle, demeure
     * réservée à 'ROLE_ADMIN' : une sortie d'argent est un document, on la corrige, on ne l'efface
     * pas depuis un guichet.
     */
    public const ENTITIES = ['Voyage', 'Ticket', 'Reservation', 'Courrier', 'Bagage', 'User', 'Role', 'Depense'];

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
