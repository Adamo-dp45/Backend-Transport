<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Arret;
use App\Entity\Interface\GareOwnedInterface;
use App\Entity\Interface\LigneGareScopedInterface;
use App\Entity\Interface\MultiGareScopedInterface;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Restriction par gare (en plus du périmètre entreprise).
 *
 * Active UNIQUEMENT pour un agent rattaché à une gare et non-admin :
 *  - admins entreprise (ROLE_ADMIN) et super-admins → voient tout (pas de filtre),
 *  - utilisateurs « centraux » sans gare → voient tout,
 *  - admin de gare + utilisateur lié à une gare → filtrés selon le périmètre de l'entité.
 *
 * S'applique aux requêtes auto de collection/item d'API Platform. Les providers personnalisés
 * (plan des sièges, bordereau, stats) utilisent des requêtes repo directes et NE sont PAS filtrés
 * — ils ont besoin des données complètes du voyage.
 */
class GareScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    )
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($resourceClass, $queryBuilder);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($resourceClass, $queryBuilder);
    }

    private function addWhere(string $resourceClass, QueryBuilder $queryBuilder): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user || $this->security->isGranted('ROLE_SUPER_ADMIN') || $this->security->isGranted('ROLE_ADMIN')) {
            return; // admins entreprise/super : aucun filtre gare
        }

        $gare = $user->getGare();
        if ($gare === null) {
            return; // utilisateur central sans gare : aucun filtre gare
        }
        $gareId = $gare->getId();
        $alias = $queryBuilder->getAllAliases()[0];

        // Périmètre C : un seul champ gare ou utiliser un 'idgae'..
        if (is_subclass_of($resourceClass, GareOwnedInterface::class)) {
            $field = $resourceClass::gareScopeField();
            $queryBuilder
                ->andWhere("$alias.$field = :gse_gare")
                ->setParameter('gse_gare', $gareId);
            return;
        }

        // Périmètre B (2 gares) : visible si l'une des gares = la sienne
        if (is_subclass_of($resourceClass, MultiGareScopedInterface::class)) {
            $or = $queryBuilder->expr()->orX();
            foreach ($resourceClass::gareScopeFields() as $field) {
                $or->add("$alias.$field = :gse_gare");
            }
            $queryBuilder
                ->andWhere($or)
                ->setParameter('gse_gare', $gareId);
            return;
        }

        // Périmètre B (ligne) : visible si la ligne (au bout du chemin) dessert sa gare (un arrêt = sa gare)
        if (is_subclass_of($resourceClass, LigneGareScopedInterface::class)) {
            $current = $alias;
            foreach ($resourceClass::ligneScopePath() as $i => $step) {
                $next = 'gse_p' . $i;
                $queryBuilder->innerJoin("$current.$step", $next);
                $current = $next;
            }
            // IMPORTANT : on filtre par sous-requête EXISTS et NON par un INNER JOIN sur
            // « arrets ». Quand la ressource est la Ligne elle-même, sa collection « arrets »
            // est sérialisée (read:Ligne) : un INNER JOIN avec WHERE gare = X la tronquerait
            // à la seule gare de l'agent (eager-loading d'API Platform) → /api/lignes/{id}
            // ne renverrait qu'un arrêt et la « gare de descente » resterait vide côté front.
            // EXISTS borne la VISIBILITÉ de la ligne sans toucher à la collection chargée.
            $sub = $queryBuilder->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Arret::class, 'gse_a')
                ->andWhere("gse_a.ligne = $current")
                ->andWhere('gse_a.gare = :gse_gare');
            $queryBuilder
                ->andWhere($queryBuilder->expr()->exists($sub->getDQL()))
                ->setParameter('gse_gare', $gareId);
            return;
        }
        // Sinon (périmètre A) : aucun filtre
    }
}
