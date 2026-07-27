<?php

namespace App\Domain\Service;

use App\Domain\Enum\AlertePortee;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Source UNIQUE de la règle d'audience des alertes (qui voit quoi), en plus du périmètre
 * entreprise (EntrepriseScopeExtension). Utilisée par AlerteAudienceExtension (pipeline API)
 * ET par AlerteRepository (contrôleurs resume / recentes / lire-tout) — la règle n'est jamais
 * dupliquée.
 *
 *  - super admin → N'EST PAS concerné (rôle plateforme, hors périmètre entreprise) : aucune alerte ;
 *  - admin entreprise / utilisateur central sans gare → voit TOUT dans son entreprise (aucun filtre) ;
 *  - agent ou admin rattaché à une gare (non admin entreprise) → alertes ENTREPRISE (partagées)
 *    OU GARE limitées à SA gare. Jamais les alertes DIRECTION (sensibles).
 */
class AlerteAudienceResolver
{
    public function __construct(private Security $security)
    {
    }

    /** Ajoute les contraintes d'audience (gare / portée) à une requête sur l'entité Alerte. */
    public function appliquer(QueryBuilder $qb, string $alias): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            // Aucun utilisateur, ou super admin (hors périmètre entreprise) : ne rien exposer.
            $qb->andWhere('1 = 0');
            return;
        }
        if ($this->voitTout($user)) {
            return;
        }
        // Agent / admin de gare : ENTREPRISE (partagé) + GARE de SA gare ; jamais DIRECTION.
        $qb->andWhere($qb->expr()->orX(
            "$alias.portee = :aud_ent",
            $qb->expr()->andX("$alias.portee = :aud_gare", "$alias.idgare = :aud_gareId")
        ))
            ->setParameter('aud_ent', AlertePortee::ENTREPRISE->value)
            ->setParameter('aud_gare', AlertePortee::GARE->value)
            ->setParameter('aud_gareId', $user->getGare()->getId());
    }

    /**
     * L'utilisateur voit-il toutes les alertes de son entreprise (aucun filtre gare/portée) ?
     * (Le super admin est déjà écarté en amont dans appliquer : il n'est pas concerné.)
     */
    public function voitTout(User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Utilisateur central sans gare : vue globale (cohérent avec les scopes existants).
        return $user->getGare() === null;
    }
}
