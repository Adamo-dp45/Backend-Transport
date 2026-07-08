<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Voyage;
use Doctrine\ORM\QueryBuilder;

/**
 * Perf : sur la COLLECTION de voyages, hydrate en UNE requête la ligne + ses arrêts + leurs gares.
 * Ainsi Voyage::getRouteEffectiveIds() (sérialisé en read:Voyage pour situer une gare sur la route)
 * lit des arrêts déjà chargés au lieu de déclencher un SELECT par ligne (N+1) sur la liste des voyages.
 *
 * Les arrêts ne sont PAS sérialisés dans read:Voyage → aucun impact sur la charge utile renvoyée.
 * API Platform gère le LIMIT avec ce fetch-join to-many via son paginator (COUNT DISTINCT + ids).
 */
final class VoyageArretsExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if ($resourceClass !== Voyage::class) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->leftJoin("$alias.ligne", 'vae_l')->addSelect('vae_l')
            ->leftJoin('vae_l.arrets', 'vae_a')->addSelect('vae_a')
            ->leftJoin('vae_a.gare', 'vae_g')->addSelect('vae_g');
    }
}
