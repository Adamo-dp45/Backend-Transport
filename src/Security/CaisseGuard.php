<?php

namespace App\Security;

use App\Entity\Sessioncaisse;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * QUI PEUT ARRÊTER UNE CAISSE — la permission dit « il sait clôturer », cette garde dit « celle-ci ».
 *
 * La distinction n'est pas théorique : 'CLOTURER' est une permission d'ENTITÉ, elle ne connaît pas
 * l'objet visé. Sans cette garde, un guichetier habilité à fermer SA caisse fermerait aussi celle
 * du collègue d'à côté — en figeant des totaux qu'il n'a pas comptés et en signant un écart au nom
 * d'un autre. C'est le pendant, côté écriture, de 'CaisseScopeExtension'.
 */
class CaisseGuard
{
    /**
     * Le TITULAIRE, son admin de gare, ou un administrateur d'entreprise.
     *
     * L'ADMIN DE GARE y est parce qu'une caisse se clôture parfois sans son titulaire : l'agent est
     * parti, il est malade, il a oublié. Quelqu'un doit pouvoir compter le tiroir et constater
     * l'écart le soir même — un constat remis au lendemain ne vaut plus rien.
     */
    public function assertPeutCloturer(User $user, Sessioncaisse $session): void
    {
        if (GareScopedEntities::isPrivileged($user)) {
            return;
        }

        if ($session->getAgent()?->getId() === $user->getId()) {
            return;
        }

        $gareAgent = $user->getGare()?->getId();

        if (
            in_array('ROLE_ADMIN_GARE', $user->getRoles(), true)
            && $gareAgent !== null
            && $session->getGare()?->getId() === $gareAgent
        ) {
            return;
        }

        throw new AccessDeniedHttpException(
            'Vous ne pouvez clôturer que votre propre caisse.'
        );
    }
}
