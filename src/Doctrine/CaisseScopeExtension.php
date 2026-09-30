<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Security\GareScopedEntities;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * UN AGENT NE VOIT QUE SES PROPRES CAISSES — en plus du périmètre entreprise et du périmètre gare.
 *
 * Une caisse porte ce qu'une personne a encaissé et ce qui lui manque le soir. Laisser un
 * guichetier lire celles de ses collègues n'ajoute rien à son travail et donne à voir, de tout
 * l'effectif d'une gare, qui est en difficulté — une information de gestion, pas d'exploitation.
 *
 * QUI ÉCHAPPE AU FILTRE, et pourquoi : l'admin d'entreprise et le super admin (rien ne les borne),
 * l'ADMIN DE GARE — surveiller les caisses de sa gare EST son métier, et 'GareScopeExtension' les
 * borne déjà à sa gare — et l'utilisateur CENTRAL sans gare, qui n'a lui-même aucune caisse et
 * n'aurait donc rien à lire du tout.
 *
 * Comme toutes les extensions de requête sont génériques (autoconfigure), on borne STRICTEMENT à
 * la ressource 'Sessioncaisse' — même précaution que 'AlerteAudienceExtension'.
 */
class CaisseScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->apply($resourceClass, $queryBuilder);
    }

    /**
     * Appliqué AUSSI à l'item : sans cela, la caisse d'un collègue reste lisible par son
     * identifiant. Et le 404 qui en résulte arrive AVANT l'évaluation du 'security:' de
     * l'opération — c'est ce qui rend l'objet introuvable plutôt que refusé, donc muet sur son
     * existence. Même procédé que 'GareScopeExtension'.
     */
    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->apply($resourceClass, $queryBuilder);
    }

    private function apply(string $resourceClass, QueryBuilder $queryBuilder): void
    {
        if ($resourceClass !== Sessioncaisse::class) {
            return;
        }

        $user = $this->security->getUser();

        /*
            !! L'UTILISATEUR PEUT ÊTRE NUL ICI : sur une collection, API Platform exécute le
            provider — donc cette extension — AVANT d'évaluer le 'security:' de l'opération. On ne
            déréférence rien et on laisse l'opération refuser (401), au lieu de produire un 500 sur
            un appel anonyme. C'est le piège documenté de 'MeProvider'.
        */
        if (!$user instanceof User) {
            return;
        }

        if (GareScopedEntities::isPrivileged($user) || in_array('ROLE_ADMIN_GARE', $user->getRoles(), true)) {
            return;
        }

        if ($user->getGare() === null) {
            return;
        }

        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.agent = :caisse_agent', $alias))
            ->setParameter('caisse_agent', $user->getId());
    }
}
